<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            ->with([
                'evidences' => static function ($q): void {
                    $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type'])->orderBy('sort_order');
                },
            ])
            ->get()
            ->map(fn (Complaint $complaint) => $this->transformComplaint($complaint));

        return response()->json($complaints);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contactMethod' => ['required', Rule::in($this->allowedContactMethods())],
            'contactValue' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in($this->allowedCategories())],
            'description' => ['required', 'string'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => $this->evidenceAttachmentRules(),
        ]);

        $this->validateContactValue($validated['contactMethod'], $validated['contactValue']);

        if ($this->isSubmissionLimitReached($validated['contactMethod'], $validated['contactValue'])) {
            $limit = $this->dailySubmissionLimit();

            return response()->json([
                'message' => "Submission limit reached. You can only submit {$limit} complaint(s) per day using the same contact.",
            ], 429);
        }

        $complaint = DB::transaction(function () use ($request, $validated) {
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

            if ($request->hasFile('evidence')) {
                /** @var array<int, \Illuminate\Http\UploadedFile> $files */
                $files = $request->file('evidence');
                foreach (array_values($files) as $i => $file) {
                    ComplaintEvidence::query()->create([
                        'complaint_id' => $record->id,
                        'sort_order' => $i,
                        'original_name' => $file->getClientOriginalName() ?: 'attachment-'.$i,
                        'mime_type' => $file->getMimeType() ?: null,
                        'file_data' => $file->getContent(),
                    ]);
                }
            }

            return $record;
        });

        $complaint->load([
            'evidences' => static function ($q): void {
                $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type'])->orderBy('sort_order');
            },
        ]);

        return response()->json([
            'message' => 'Complaint submitted successfully.',
            'complaint' => $this->transformComplaint($complaint),
        ], 201);
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
            return response()->file($disk->path($key));
        }

        if (! $disk->exists($key)) {
            return response()->json([
                'message' => 'Evidence file does not exist on disk.',
            ], 404);
        }

        $mime = $disk->mimeType($key) ?? 'application/octet-stream';
        $filename = basename($key);

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
        $evidenceUrls = collect($evidencePaths)
            ->values()
            ->map(fn (string $value, int $index): string => secure_url("/api/complaints/{$complaint->id}/evidence/{$index}"))
            ->all();

        return [
            'id' => $complaint->id,
            'trackingNumber' => $complaint->tracking_number,
            'residentName' => $complaint->resident_name,
            'contactMethod' => $complaint->contact_method ?? Complaint::CONTACT_METHOD_PHONE,
            'contactValue' => $complaint->contact_value ?? $complaint->contact_number,
            'contactNumber' => $complaint->contact_value ?? $complaint->contact_number,
            'category' => $complaint->category,
            'description' => $complaint->description,
            'status' => $complaint->status,
            'dateSubmitted' => optional($complaint->date_submitted)->toDateString(),
            'evidencePath' => $evidencePaths[0] ?? null,
            'evidenceUrl' => $evidenceUrls[0] ?? null,
            'evidencePaths' => $evidencePaths,
            'evidenceUrls' => $evidenceUrls,
            'assignedOfficer' => $complaint->assigned_officer,
            'adminNotes' => $complaint->admin_notes,
            'createdAt' => optional($complaint->created_at)->toISOString(),
            'updatedAt' => optional($complaint->updated_at)->toISOString(),
        ];
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
                    'to' => [$emailAddress],
                    'subject' => "Official Complaint Status Update - {$complaint->tracking_number}",
                    'text' => $message,
                ])
                ->throw();

            return [
                'sent' => true,
                'reason' => null,
            ];
        } catch (RequestException $exception) {
            Log::error('Failed to send complaint status email.', [
                'email' => $emailAddress,
                'complaint_id' => $complaint->id,
                'status' => optional($exception->response)->status(),
                'body' => optional($exception->response)->body(),
                'error' => $exception->getMessage(),
            ]);

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
                    $q->select(['id', 'complaint_id', 'sort_order', 'original_name', 'mime_type'])->orderBy('sort_order');
                },
            ]);
        }

        if ($complaint->evidences->isNotEmpty()) {
            return $complaint->evidences->pluck('original_name')->all();
        }

        $paths = $complaint->evidence_paths;
        if (is_array($paths) && count($paths) > 0) {
            return array_values(array_filter(array_map('strval', $paths)));
        }

        return $complaint->evidence_path ? [$complaint->evidence_path] : [];
    }
}
