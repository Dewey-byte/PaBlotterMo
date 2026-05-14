<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarangaySetting;
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

        $complaints = $query
            ->latest('date_submitted')
            ->get()
            ->map(fn (Complaint $complaint) => $this->transformComplaint($complaint));

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
                'description' => ['required', 'string'],
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
        return response()->json($this->transformComplaint($complaint));
    }

    public function evidence(Complaint $complaint, int $index = 0): BinaryFileResponse|JsonResponse|StreamedResponse
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

        return response()->json($this->transformComplaint($complaint));
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in($this->allowedStatuses())],
            'adminNotes' => ['sometimes', 'nullable', 'string'],
            'assignedOfficer' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $originalStatus = $complaint->status;

        $complaint->fill([
            'status' => $validated['status'] ?? $complaint->status,
            'admin_notes' => array_key_exists('adminNotes', $validated) ? $validated['adminNotes'] : $complaint->admin_notes,
            'assigned_officer' => array_key_exists('assignedOfficer', $validated) ? $validated['assignedOfficer'] : $complaint->assigned_officer,
        ]);
        $complaint->save();

        $statusChanged = $originalStatus !== $complaint->status;
        $notificationSent = true;
        $notificationReason = null;

        if ($statusChanged) {
            $notificationResult = $this->notifyComplainantStatusUpdate($complaint, $originalStatus);
            $notificationSent = $notificationResult['sent'];
            $notificationReason = $notificationResult['reason'];
        }

        $message = 'Complaint updated successfully.';
        if ($statusChanged && ! $notificationSent) {
            $message .= ' Status was updated, but notification could not be sent.';
        }

        return response()->json([
            'message' => $message,
            'complaint' => $this->transformComplaint($complaint),
            'notificationSent' => $statusChanged ? $notificationSent : null,
            'notificationReason' => $statusChanged ? $notificationReason : null,
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
        return response()->json([
            'total' => Complaint::query()->count(),
            'pending' => Complaint::query()->where('status', Complaint::STATUS_PENDING)->count(),
            'investigating' => Complaint::query()->where('status', Complaint::STATUS_UNDER_INVESTIGATION)->count(),
            'resolved' => Complaint::query()->where('status', Complaint::STATUS_RESOLVED)->count(),
            'rejected' => Complaint::query()->where('status', Complaint::STATUS_REJECTED)->count(),
        ]);
    }

    private function transformComplaint(Complaint $complaint): array
    {
        $evidencePaths = $this->complaintEvidencePaths($complaint);
        $count = count($evidencePaths);
        $evidenceUrls = $count > 0
            ? array_map(
                fn (int $i): string => secure_url("/api/complaints/{$complaint->id}/evidence/{$i}"),
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
    private function notifyComplainantStatusUpdate(Complaint $complaint, string $previousStatus): array
    {
        $contactMethod = $complaint->contact_method ?? Complaint::CONTACT_METHOD_PHONE;
        $contactValue = $complaint->contact_value ?? $complaint->contact_number;

        if (! $contactValue) {
            return [
                'sent' => false,
                'reason' => 'No contact value found for this complaint.',
            ];
        }

        $message = implode("\n", [
            'Dear Concerned Resident,',
            '',
            "This is to inform you that the status of your complaint ({$complaint->tracking_number}) has been updated.",
            "Previous status: {$previousStatus}",
            "Current status: {$complaint->status}",
            '',
            $complaint->admin_notes
                ? "Administrative notes: {$complaint->admin_notes}"
                : 'No additional notes were provided at this time.',
            '',
            'Thank you for your patience and cooperation.',
            'Barangay Complaint Management Team',
        ]);

        return match ($contactMethod) {
            Complaint::CONTACT_METHOD_EMAIL => $this->sendStatusUpdateEmail($contactValue, $message, $complaint),
            Complaint::CONTACT_METHOD_PHONE => $this->sendStatusUpdateSms($contactValue, $message),
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
            $adminHint = $baseUrl !== '' ? "{$baseUrl}/admin" : 'Open the PaBlotterMo admin dashboard.';

            $barangayLabel = trim((string) $settings->barangay_name) !== ''
                ? $settings->barangay_name
                : 'Your barangay';

            $body = implode("\n", [
                'A new complaint was submitted through PaBlotterMo.',
                '',
                "Tracking number: {$complaint->tracking_number}",
                "Category: {$complaint->category}",
                'Contact channel used by resident: '.$contactChannel,
                '',
                'Description (excerpt):',
                $excerpt !== '' ? $excerpt : '(none)',
                '',
                'Review this complaint in the admin dashboard:',
                $adminHint,
                '',
                '— '.$barangayLabel.' (automated notice)',
            ]);

            $subject = "New complaint: {$complaint->tracking_number} ({$complaint->category})";

            $result = $this->sendResendEmails($recipients, $subject, $body, [
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
    private function sendResendEmails(array $toAddresses, string $subject, string $textBody, array $logContext = []): array
    {
        $apiKey = (string) env('RESEND_API_KEY', '');
        $fromAddress = (string) env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');

        if ($apiKey === '' || str_contains($apiKey, 'your_resend_api_key')) {
            return [
                'sent' => false,
                'reason' => 'Resend email provider is not configured.',
            ];
        }

        try {
            Http::withToken($apiKey)
                ->acceptJson()
                ->post('https://api.resend.com/emails', [
                    'from' => $fromAddress,
                    'to' => array_values($toAddresses),
                    'subject' => $subject,
                    'text' => $textBody,
                ])
                ->throw();

            return [
                'sent' => true,
                'reason' => null,
            ];
        } catch (RequestException $exception) {
            Log::error('Resend email request failed.', array_merge($logContext, [
                'subject' => $subject,
                'status' => optional($exception->response)->status(),
                'body' => optional($exception->response)->body(),
                'error' => $exception->getMessage(),
            ]));

            $providerMessage = trim($exception->getMessage());
            $providerMessage = $providerMessage !== '' ? " Provider said: {$providerMessage}" : '';

            return [
                'sent' => false,
                'reason' => "Email provider rejected the message.{$providerMessage}",
            ];
        }
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    private function sendStatusUpdateEmail(string $emailAddress, string $message, Complaint $complaint): array
    {
        if (! filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'reason' => 'Invalid email address format.',
            ];
        }

        return $this->sendResendEmails(
            [$emailAddress],
            "Official Complaint Status Update - {$complaint->tracking_number}",
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
