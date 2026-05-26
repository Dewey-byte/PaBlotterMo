<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarangaySetting;
use App\Services\TransactionalEmailService;
use App\Support\ComplaintNotificationTemplates;
use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('resident_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status !== 'All') {
                $query->where('status', $status);
            }
        }

        if ($category = $request->string('category')->toString()) {
            if ($category !== 'All') {
                $query->where('category', $category);
            }
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $paginator = $query
            ->select([
                'id',
                'tracking_number',
                'resident_name',
                'contact_method',
                'contact_value',
                'contact_number',
                'category',
                'description',
                'status',
                'date_submitted',
                'assigned_officer',
                'admin_notes',
                'created_at',
                'updated_at',
            ])
            ->latest('date_submitted')
            ->paginate($perPage)
            ->through(fn (Complaint $complaint) => $this->transformComplaintSummary($complaint));

        return response()->json($paginator);
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 5), 1), 20);

        $complaints = Complaint::query()
            ->select([
                'id',
                'tracking_number',
                'resident_name',
                'contact_method',
                'contact_value',
                'contact_number',
                'category',
                'description',
                'status',
                'date_submitted',
                'assigned_officer',
                'admin_notes',
                'created_at',
                'updated_at',
            ])
            ->latest('date_submitted')
            ->limit($limit)
            ->get()
            ->map(fn (Complaint $complaint) => $this->transformComplaintSummary($complaint));

        return response()->json($complaints);
    }

    public function store(Request $request): JsonResponse
    {
        $evidenceFiles = $this->normalizeEvidenceUploads($request);

        $validated = Validator::make(
            array_merge(
                $request->only(['contactMethod', 'contactValue', 'category', 'description']),
                ['evidence' => $evidenceFiles]
            ),
            [
                'contactMethod' => ['required', Rule::in($this->allowedContactMethods())],
                'contactValue' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in($this->allowedCategories())],
                'description' => ['required', 'string', 'min:10', 'max:5000'],
                'evidence' => ['nullable', 'array', 'max:5'],
                'evidence.*' => $this->evidenceAttachmentRules(),
            ]
        )->validate();

        $this->validateContactValue($validated['contactMethod'], $validated['contactValue']);

        if ($this->isSubmissionLimitReached($validated['contactMethod'], $validated['contactValue'])) {
            $limit = $this->dailySubmissionLimit();

            return response()->json([
                'message' => "Submission limit reached. You can only submit {$limit} complaint(s) per day using the same contact.",
            ], 429);
        }

        $complaint = DB::transaction(function () use ($validated, $evidenceFiles) {
            $record = Complaint::query()->create([
                'tracking_number' => $this->generateTrackingNumber(),
                'resident_name' => 'Anonymous',
                'contact_number' => $validated['contactValue'],
                'contact_method' => $validated['contactMethod'],
                'contact_value' => $validated['contactValue'],
                'category' => $validated['category'],
                'description' => $validated['description'],
                'status' => Complaint::STATUS_PENDING,
                'date_submitted' => now(),
                'evidence_path' => null,
                'evidence_paths' => null,
            ]);

            foreach ($evidenceFiles as $i => $file) {
                ComplaintEvidence::query()->create([
                    'complaint_id' => $record->id,
                    'sort_order' => $i,
                    'original_name' => $file->getClientOriginalName() ?: 'attachment-'.$i,
                    'mime_type' => $file->getMimeType() ?: null,
                    'file_data' => $file->getContent(),
                ]);
            }

            return $record;
        });

        $complaint->load([
            'evidences' => static function ($q): void {
                $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type']);
            },
        ]);

        $this->sendNewComplaintAdminNotifications($complaint);

        return response()->json([
            'message' => 'Complaint submitted successfully.',
            'complaint' => $this->transformComplaint($complaint),
        ], 201);
    }

    /**
     * Multipart file fields vary by client (evidence, evidence[], evidence[0], …). Normalize so validation and DB save always run.
     *
     * @return list<UploadedFile>
     */
    private function normalizeEvidenceUploads(Request $request): array
    {
        $out = [];

        foreach ($request->allFiles() as $name => $file) {
            if (! is_string($name)) {
                continue;
            }

            if ($name !== 'evidence' && ! str_starts_with($name, 'evidence[')) {
                continue;
            }

            $items = is_array($file) ? $file : [$file];
            foreach ($items as $item) {
                if ($item instanceof UploadedFile && $item->isValid()) {
                    $out[] = $item;
                }
            }
        }

        return array_slice($out, 0, 5);
    }

    /**
     * iPhone HEIC uploads often report as application/octet-stream, so Laravel's mimes rule rejects them.
     *
     * @return array<int, \Closure|string>
     */
    private function evidenceAttachmentRules(): array
    {
        return [
            'file',
            'max:51200',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $allowedExtensions = [
                    'jpg', 'jpeg', 'jfif', 'png', 'webp', 'heic', 'heif',
                    'pdf', 'mp4', 'mov', 'm4v', 'webm', 'ogg', '3gp',
                ];

                $ext = strtolower($value->getClientOriginalExtension());
                if ($ext === '') {
                    $ext = strtolower((string) $value->guessExtension());
                }

                $mime = strtolower((string) $value->getMimeType());
                if (in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence'], true)) {
                    return;
                }

                if (! in_array($ext, $allowedExtensions, true)) {
                    $fail('Each attachment must be an image (including iPhone HEIC), PDF, or a supported video format.');
                }
            },
        ];
    }

    public function show(Complaint $complaint): JsonResponse
    {
        $complaint->load([
            'evidences' => static function ($q): void {
                $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type']);
            },
        ]);

        return response()->json($this->transformComplaint($complaint));
    }

    public function evidence(Request $request, Complaint $complaint, int $index = 0): BinaryFileResponse|JsonResponse|StreamedResponse
    {
        if ($complaint->evidences()->exists()) {
            $attachment = $complaint->evidences()->orderBy('sort_order')->skip($index)->first();
            if (! $attachment) {
                return response()->json([
                    'message' => 'No evidence file found for this complaint.',
                ], 404);
            }

            $mime = $attachment->mime_type ?: 'application/octet-stream';
            $filename = basename($attachment->original_name) ?: 'evidence';
            $mime = $this->coerceEvidenceContentType($mime, $filename);

            $preview = $this->tryHeicJpegPreviewResponse($request, $mime, (string) $attachment->file_data, $filename);
            if ($preview !== null) {
                return $preview;
            }

            return response()->stream(function () use ($attachment): void {
                echo $attachment->file_data;
            }, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $paths = $this->complaintEvidencePaths($complaint);
        $selectedPath = $paths[$index] ?? null;

        if (! $selectedPath) {
            return response()->json([
                'message' => 'No evidence file found for this complaint.',
            ], 404);
        }

        $key = $this->evidenceStorageKey($selectedPath);
        $diskName = $this->resolveEvidenceReadDisk($key);

        if ($diskName === null) {
            return response()->json([
                'message' => 'Evidence file does not exist on disk.',
            ], 404);
        }

        $disk = Storage::disk($diskName);

        if ($diskName === 'public') {
            $absolutePath = $disk->path($key);
            $filename = basename($key);
            $mime = $disk->mimeType($key) ?? 'application/octet-stream';
            $mime = $this->coerceEvidenceContentType($mime, $filename);

            if ($this->wantsHeicPreview($request, $mime)) {
                $binary = @file_get_contents($absolutePath);
                if (is_string($binary) && $binary !== '') {
                    $preview = $this->tryHeicJpegPreviewResponse($request, $mime, $binary, $filename);
                    if ($preview !== null) {
                        return $preview;
                    }
                }
            }

            return response()->file($absolutePath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        if (! $disk->exists($key)) {
            return response()->json([
                'message' => 'Evidence file does not exist on disk.',
            ], 404);
        }

        $mime = $disk->mimeType($key) ?? 'application/octet-stream';
        $filename = basename($key);
        $mime = $this->coerceEvidenceContentType($mime, $filename);

        $preview = null;
        if ($this->wantsHeicPreview($request, $mime)) {
            $binary = $disk->get($key);
            if (is_string($binary) && $binary !== '') {
                $preview = $this->tryHeicJpegPreviewResponse($request, $mime, $binary, $filename);
            }
        }
        if ($preview !== null) {
            return $preview;
        }

        return response()->stream(function () use ($disk, $key): void {
            $stream = $disk->readStream($key);
            if (! is_resource($stream)) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function track(string $trackingNumber): JsonResponse
    {
        $complaint = Complaint::query()
            ->where('tracking_number', strtoupper(trim($trackingNumber)))
            ->first();

        if (! $complaint) {
            return response()->json([
                'message' => 'Complaint not found for the provided tracking number.',
            ], 404);
        }

        $complaint->load([
            'evidences' => static function ($q): void {
                $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type']);
            },
        ]);

        return response()->json($this->transformComplaint($complaint));
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in($this->allowedStatuses())],
            'adminNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assignedOfficer' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $originalStatus = $complaint->status;
        $originalNotes = trim((string) ($complaint->admin_notes ?? ''));

        $complaint->fill([
            'status' => $validated['status'] ?? $complaint->status,
            'admin_notes' => array_key_exists('adminNotes', $validated) ? $validated['adminNotes'] : $complaint->admin_notes,
            'assigned_officer' => array_key_exists('assignedOfficer', $validated) ? $validated['assignedOfficer'] : $complaint->assigned_officer,
        ]);
        $complaint->save();
        $complaint->refresh();
        $complaint->load([
            'evidences' => static function ($q): void {
                $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type']);
            },
        ]);

        $statusChanged = $originalStatus !== $complaint->status;
        $adminNotesForNotification = trim((string) ($complaint->admin_notes ?? ''));
        $notesChanged = $originalNotes !== $adminNotesForNotification;
        $shouldNotifyComplainant = $statusChanged || ($notesChanged && $adminNotesForNotification !== '');

        $notificationSent = true;
        $notificationReason = null;

        if ($shouldNotifyComplainant) {
            $notificationResult = $this->notifyComplainantStatusUpdate(
                $complaint,
                $originalStatus,
                $adminNotesForNotification,
                $statusChanged,
            );
            $notificationSent = $notificationResult['sent'];
            $notificationReason = $notificationResult['reason'];
        }

        $message = 'The complaint record has been updated successfully.';
        if ($shouldNotifyComplainant && ! $notificationSent) {
            $message .= ' However, the complainant could not be notified at this time.';
        }

        return response()->json([
            'message' => $message,
            'complaint' => $this->transformComplaint($complaint),
            'notificationSent' => $shouldNotifyComplainant ? $notificationSent : null,
            'notificationReason' => $shouldNotifyComplainant ? $notificationReason : null,
        ]);
    }

    public function destroy(Complaint $complaint): JsonResponse
    {
        if (! $complaint->evidences()->exists()) {
            foreach ($this->complaintEvidencePaths($complaint) as $path) {
                $key = $this->evidenceStorageKey($path);
                $diskName = $this->resolveEvidenceReadDisk($key);
                if ($diskName !== null) {
                    Storage::disk($diskName)->delete($key);
                }
            }
        }

        $complaint->delete();

        return response()->json([
            'message' => 'Complaint deleted successfully.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $row = Complaint::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [Complaint::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as investigating', [Complaint::STATUS_UNDER_INVESTIGATION])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved', [Complaint::STATUS_RESOLVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [Complaint::STATUS_REJECTED])
            ->first();

        return response()->json([
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'investigating' => (int) ($row->investigating ?? 0),
            'resolved' => (int) ($row->resolved ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
        ]);
    }

    /**
     * Lightweight payload for admin list and dashboard widgets (no evidence metadata).
     *
     * @return array<string, mixed>
     */
    private function transformComplaintSummary(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'trackingNumber' => $this->utf8JsonString($complaint->tracking_number),
            'residentName' => $this->utf8JsonString($complaint->resident_name),
            'contactMethod' => $this->utf8JsonString($complaint->contact_method) ?? Complaint::CONTACT_METHOD_PHONE,
            'contactValue' => $this->utf8JsonString($complaint->contact_value ?? $complaint->contact_number),
            'contactNumber' => $this->utf8JsonString($complaint->contact_value ?? $complaint->contact_number),
            'category' => $this->utf8JsonString($complaint->category),
            'description' => $this->utf8JsonString($complaint->description),
            'status' => $this->utf8JsonString($complaint->status),
            'dateSubmitted' => optional($complaint->date_submitted)->toDateString(),
            'assignedOfficer' => $this->utf8JsonString($complaint->assigned_officer),
            'adminNotes' => $this->utf8JsonString($complaint->admin_notes),
            'createdAt' => $complaint->created_at?->toIso8601String(),
            'updatedAt' => $complaint->updated_at?->toIso8601String(),
        ];
    }

    private function transformComplaint(Complaint $complaint): array
    {
        $evidencePaths = $this->complaintEvidencePaths($complaint);
        $count = count($evidencePaths);
        $evidenceUrls = $count > 0
            ? array_map(
                fn (int $i): string => url("/api/complaints/{$complaint->id}/evidence/{$i}"),
                range(0, $count - 1)
            )
            : [];

        $evidenceMimeTypes = $complaint->evidences->isNotEmpty()
            ? $complaint->evidences
                ->map(function ($evidence): ?string {
                    $mime = $evidence->mime_type;

                    return is_string($mime) && trim($mime) !== '' ? strtolower(trim($mime)) : null;
                })
                ->values()
                ->all()
            : array_fill(0, $count, null);

        return [
            'id' => $complaint->id,
            'trackingNumber' => $this->utf8JsonString($complaint->tracking_number),
            'residentName' => $this->utf8JsonString($complaint->resident_name),
            'contactMethod' => $this->utf8JsonString($complaint->contact_method) ?? Complaint::CONTACT_METHOD_PHONE,
            'contactValue' => $this->utf8JsonString($complaint->contact_value ?? $complaint->contact_number),
            'contactNumber' => $this->utf8JsonString($complaint->contact_value ?? $complaint->contact_number),
            'category' => $this->utf8JsonString($complaint->category),
            'description' => $this->utf8JsonString($complaint->description),
            'status' => $this->utf8JsonString($complaint->status),
            'dateSubmitted' => optional($complaint->date_submitted)->toDateString(),
            'evidencePath' => isset($evidencePaths[0]) ? $this->utf8JsonString($evidencePaths[0]) : null,
            'evidenceUrl' => $evidenceUrls[0] ?? null,
            'evidencePaths' => array_map(
                fn ($p): string => $this->utf8JsonString((string) $p) ?? '',
                $evidencePaths
            ),
            'evidenceUrls' => $evidenceUrls,
            'evidenceMimeTypes' => $evidenceMimeTypes,
            'assignedOfficer' => $this->utf8JsonString($complaint->assigned_officer),
            'adminNotes' => $this->utf8JsonString($complaint->admin_notes),
            'createdAt' => $complaint->created_at?->toIso8601String(),
            'updatedAt' => $complaint->updated_at?->toIso8601String(),
        ];
    }

    private function utf8JsonString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean === false ? '' : $clean;
    }

    private function generateTrackingNumber(): string
    {
        do {
            $trackingNumber = 'BCM-'.random_int(10000000, 99999999);
        } while (Complaint::query()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        return [
            Complaint::STATUS_PENDING,
            Complaint::STATUS_UNDER_INVESTIGATION,
            Complaint::STATUS_RESOLVED,
            Complaint::STATUS_REJECTED,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedCategories(): array
    {
        return [
            Complaint::CATEGORY_NOISE,
            Complaint::CATEGORY_THEFT,
            Complaint::CATEGORY_DOMESTIC,
            Complaint::CATEGORY_PROPERTY,
            Complaint::CATEGORY_OTHERS,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedContactMethods(): array
    {
        return [
            Complaint::CONTACT_METHOD_PHONE,
            Complaint::CONTACT_METHOD_EMAIL,
        ];
    }

    private function validateContactValue(string $contactMethod, string $contactValue): void
    {
        if ($contactMethod === Complaint::CONTACT_METHOD_EMAIL) {
            validator(
                ['contactValue' => $contactValue],
                ['contactValue' => ['required', 'email']]
            )->validate();

            return;
        }

        validator(
            ['contactValue' => $contactValue],
            ['contactValue' => ['required', 'regex:/^[0-9+\-\s()]{7,20}$/']]
        )->validate();
    }

    private function isSubmissionLimitReached(string $contactMethod, string $contactValue): bool
    {
        $count = Complaint::query()
            ->where('contact_method', $contactMethod)
            ->where('contact_value', $contactValue)
            ->where('date_submitted', '>=', Carbon::today())
            ->count();

        return $count >= $this->dailySubmissionLimit();
    }

    private function dailySubmissionLimit(): int
    {
        return (int) env('COMPLAINT_DAILY_SUBMISSION_LIMIT', 3);
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    private function notifyComplainantStatusUpdate(
        Complaint $complaint,
        string $previousStatus,
        string $adminNotes,
        bool $statusChanged = true,
    ): array {
        $contactMethod = $complaint->contact_method ?? Complaint::CONTACT_METHOD_PHONE;
        $contactValue = $complaint->contact_value ?? $complaint->contact_number;

        if (! $contactValue) {
            return [
                'sent' => false,
                'reason' => 'No contact value found for this complaint.',
            ];
        }

        $settings = BarangaySetting::query()->first();
        $barangayName = ComplaintNotificationTemplates::barangayName($settings);

        return match ($contactMethod) {
            Complaint::CONTACT_METHOD_EMAIL => $this->sendStatusUpdateEmail(
                $contactValue,
                ComplaintNotificationTemplates::residentStatusEmailSubject($complaint),
                ComplaintNotificationTemplates::residentStatusEmailBody(
                    $complaint,
                    $previousStatus,
                    $barangayName,
                    $adminNotes,
                    $statusChanged,
                ),
                $complaint,
            ),
            Complaint::CONTACT_METHOD_PHONE => $this->sendStatusUpdateSms(
                $contactValue,
                ComplaintNotificationTemplates::residentStatusSmsBody(
                    $complaint,
                    $previousStatus,
                    $barangayName,
                    $adminNotes,
                    $statusChanged,
                ),
            ),
            default => [
                'sent' => false,
                'reason' => 'Unsupported contact method.',
            ],
        };
    }

    /**
     * Notify barangay contact email and admin users when a new complaint is filed (best-effort; never throws).
     */
    private function sendNewComplaintAdminNotifications(Complaint $complaint): void
    {
        try {
            $settings = BarangaySetting::query()->first();
            if ($settings === null || ! $settings->notify_email_new_complaints) {
                return;
            }

            $recipients = $this->newComplaintNotificationRecipients($settings);
            if ($recipients === []) {
                Log::warning('New complaint email notification skipped: no valid recipient addresses.', [
                    'complaint_id' => $complaint->id,
                ]);

                return;
            }

            $contactChannel = $complaint->contact_method ?? Complaint::CONTACT_METHOD_PHONE;
            $excerpt = Str::limit(trim(strip_tags((string) $complaint->description)), 280);

            $baseUrl = rtrim((string) (env('APP_URL') ?: config('app.url')), '/');
            $adminHint = $baseUrl !== ''
                ? "{$baseUrl}/admin"
                : 'Please access the PaBlotterMo administrative dashboard through your authorized link.';

            $barangayLabel = ComplaintNotificationTemplates::barangayName($settings);

            $body = ComplaintNotificationTemplates::adminNewComplaintBody(
                $complaint,
                $contactChannel,
                $excerpt,
                $adminHint,
                $barangayLabel,
            );

            $subject = ComplaintNotificationTemplates::adminNewComplaintSubject($complaint);

            $result = $this->sendTransactionalEmails($recipients, $subject, $body, [
                'complaint_id' => $complaint->id,
                'context' => 'new_complaint_admin_notice',
            ]);

            if (! $result['sent']) {
                Log::warning('New complaint admin email was not sent.', [
                    'complaint_id' => $complaint->id,
                    'reason' => $result['reason'],
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Unexpected error while sending new complaint admin notification.', [
                'complaint_id' => $complaint->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<non-empty-string>
     */
    private function newComplaintNotificationRecipients(BarangaySetting $settings): array
    {
        $normalized = [];

        $contactEmail = trim((string) $settings->contact_email);
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $normalized[strtolower($contactEmail)] = $contactEmail;
        }

        $adminEmails = User::query()
            ->where('role', 'admin')
            ->pluck('email');

        foreach ($adminEmails as $email) {
            $email = trim((string) $email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $normalized[strtolower($email)] = $email;
        }

        return array_values($normalized);
    }

    /**
     * @param  non-empty-list<string>  $toAddresses
     * @param  array<string, mixed>  $logContext
     * @return array{sent: bool, reason: string|null}
     */
    private function sendTransactionalEmails(array $toAddresses, string $subject, string $textBody, array $logContext = []): array
    {
        return app(TransactionalEmailService::class)->send($toAddresses, $subject, $textBody, $logContext);
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    private function sendStatusUpdateEmail(string $emailAddress, string $subject, string $message, Complaint $complaint): array
    {
        if (! filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'reason' => 'Invalid email address format.',
            ];
        }

        return $this->sendTransactionalEmails(
            [$emailAddress],
            $subject,
            $message,
            [
                'complaint_id' => $complaint->id,
                'context' => 'complaint_status_update',
                'resident_email' => $emailAddress,
            ]
        );
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    private function sendStatusUpdateSms(string $phoneNumber, string $message): array
    {
        $normalizedPhone = $this->normalizePhilippinePhoneNumber($phoneNumber);
        if (! $normalizedPhone) {
            return [
                'sent' => false,
                'reason' => 'Invalid phone number format.',
            ];
        }

        $apiKey = env('SEMAPHORE_API_KEY');
        $senderName = env('SEMAPHORE_SENDER_NAME');
        if (! $apiKey) {
            Log::info('Complaint status SMS fallback (Semaphore not configured).', [
                'to' => $normalizedPhone,
                'message' => $message,
            ]);

            return [
                'sent' => false,
                'reason' => 'Semaphore API key is not configured.',
            ];
        }

        $payload = [
            'api_key' => $apiKey,
            'number' => $normalizedPhone,
            'message' => $message,
        ];

        if ($senderName) {
            $payload['sendername'] = $senderName;
        }

        try {
            Http::asForm()
                ->post('https://semaphore.co/api/v4/messages', $payload)
                ->throw();

            return [
                'sent' => true,
                'reason' => null,
            ];
        } catch (RequestException $exception) {
            Log::error('Semaphore status SMS sending failed.', [
                'status' => optional($exception->response)->status(),
                'body' => optional($exception->response)->body(),
                'to' => $normalizedPhone,
            ]);

            return [
                'sent' => false,
                'reason' => 'SMS provider rejected the message.',
            ];
        }
    }

    private function normalizePhilippinePhoneNumber(?string $phoneNumber): ?string
    {
        if (! $phoneNumber) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phoneNumber);
        if (! $normalized) {
            return null;
        }

        if (str_starts_with($normalized, '09') && strlen($normalized) === 11) {
            return '+63'.substr($normalized, 1);
        }

        if (str_starts_with($normalized, '639') && strlen($normalized) === 12) {
            return '+'.$normalized;
        }

        if (str_starts_with($normalized, '9') && strlen($normalized) === 10) {
            return '+63'.$normalized;
        }

        return null;
    }

    private function evidenceWriteDisk(): string
    {
        return (string) config('complaint_evidence.disk', 'public');
    }

    /**
     * Prefer the configured read disk, then fall back to "public" for legacy rows.
     *
     * @return non-empty-string|null
     */
    private function resolveEvidenceReadDisk(string $key): ?string
    {
        $primary = $this->evidenceWriteDisk();

        if (Storage::disk($primary)->exists($key)) {
            return $primary;
        }

        if ($primary !== 'public' && Storage::disk('public')->exists($key)) {
            return 'public';
        }

        return null;
    }

    /**
     * Database may store a storage key (complaints/…), a /storage/ URL path, or a full URL.
     */
    private function evidenceStorageKey(string $storedUrlOrPath): string
    {
        $storedUrlOrPath = trim($storedUrlOrPath);

        if (str_contains($storedUrlOrPath, '/storage/')) {
            return Str::after($storedUrlOrPath, '/storage/');
        }

        return ltrim($storedUrlOrPath, '/');
    }

    /**
     * @return array<int, string>
     */
    private function complaintEvidencePaths(Complaint $complaint): array
    {
        if (! $complaint->relationLoaded('evidences')) {
            $complaint->load([
                'evidences' => static function ($q): void {
                    $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type']);
                },
            ]);
        }

        if ($complaint->evidences->isNotEmpty()) {
            return $complaint->evidences
                ->pluck('original_name')
                ->map(fn ($name): string => $name !== null && $name !== '' ? (string) $name : 'attachment')
                ->values()
                ->all();
        }

        $paths = $complaint->evidence_paths;
        if (is_array($paths) && count($paths) > 0) {
            return array_values(array_filter(array_map('strval', $paths)));
        }

        return $complaint->evidence_path ? [$complaint->evidence_path] : [];
    }

    /**
     * When ?preview=1 is set and the file is HEIC/HEIF, attempt on-the-fly JPEG for admin inline display.
     * Requires PHP Imagick with HEIF/libheif delegates; returns null when conversion is unavailable.
     */
    private function wantsHeicPreview(Request $request, string $mime): bool
    {
        if (! $request->boolean('preview')) {
            return false;
        }

        return $this->isHeicFamilyMime($mime);
    }

    private function isHeicFamilyMime(string $mime): bool
    {
        $m = strtolower(trim($mime));

        return $m === 'image/heic'
            || $m === 'image/heif'
            || str_contains($m, 'heic')
            || str_contains($m, 'heif');
    }

    private function tryHeicJpegPreviewResponse(Request $request, string $mime, string $binary, string $filename): ?StreamedResponse
    {
        if (! $this->wantsHeicPreview($request, $mime)) {
            return null;
        }

        $jpeg = $this->convertHeicBlobToJpegIfPossible($binary);
        if ($jpeg === null || $jpeg === '') {
            return null;
        }

        $previewName = pathinfo($filename, PATHINFO_FILENAME);
        $previewName = ($previewName !== '' ? $previewName : 'evidence').'-preview.jpg';

        return response()->stream(function () use ($jpeg): void {
            echo $jpeg;
        }, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.$previewName.'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function convertHeicBlobToJpegIfPossible(string $blob): ?string
    {
        if ($blob === '') {
            return null;
        }

        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $im = new \Imagick;
            $im->readImageBlob($blob);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(88);
            $im->stripImage();
            $out = $im->getImageBlob();
            $im->clear();
            $im->destroy();

            if (! is_string($out) || $out === '') {
                return null;
            }

            return $out;
        } catch (Throwable $e) {
            Log::info('HEIC→JPEG preview skipped (Imagick cannot decode or HEIF missing).', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * iPhone uploads may be stored as application/octet-stream; map known extensions so clients get a useful Content-Type.
     */
    private function coerceEvidenceContentType(string $mime, string $filename): string
    {
        $mimeNorm = strtolower($mime);

        if ($mimeNorm !== 'application/octet-stream' && $mimeNorm !== '') {
            return $mime;
        }

        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'jpg', 'jpeg', 'jfif' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mov', 'qt' => 'video/quicktime',
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            'ogg' => 'video/ogg',
            '3gp' => 'video/3gpp',
            default => $mimeNorm !== '' ? $mime : 'application/octet-stream',
        };
    }
}
