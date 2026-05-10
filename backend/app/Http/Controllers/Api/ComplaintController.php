<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'contactNumber' => ['required', 'string', 'max:30'],
            'category' => ['required', Rule::in($this->allowedCategories())],
            'description' => ['required', 'string'],
            'evidencePath' => ['nullable', 'string', 'max:255'],
        ]);

        $complaint = Complaint::query()->create([
            'tracking_number' => $this->generateTrackingNumber(),
            'resident_name' => $validated['fullName'],
            'contact_number' => $validated['contactNumber'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'status' => Complaint::STATUS_PENDING,
            'date_submitted' => now(),
            'evidence_path' => $validated['evidencePath'] ?? null,
        ]);

        return response()->json([
            'message' => 'Complaint submitted successfully.',
            'complaint' => $this->transformComplaint($complaint),
        ], 201);
    }

    public function show(Complaint $complaint): JsonResponse
    {
        return response()->json($this->transformComplaint($complaint));
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

        $complaint->fill([
            'status' => $validated['status'] ?? $complaint->status,
            'admin_notes' => array_key_exists('adminNotes', $validated) ? $validated['adminNotes'] : $complaint->admin_notes,
            'assigned_officer' => array_key_exists('assignedOfficer', $validated) ? $validated['assignedOfficer'] : $complaint->assigned_officer,
        ]);
        $complaint->save();

        return response()->json([
            'message' => 'Complaint updated successfully.',
            'complaint' => $this->transformComplaint($complaint),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Complaint::query()->count(),
            'pending' => Complaint::query()->where('status', Complaint::STATUS_PENDING)->count(),
            'investigating' => Complaint::query()->where('status', Complaint::STATUS_UNDER_INVESTIGATION)->count(),
            'resolved' => Complaint::query()->where('status', Complaint::STATUS_RESOLVED)->count(),
        ]);
    }

    private function transformComplaint(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'trackingNumber' => $complaint->tracking_number,
            'residentName' => $complaint->resident_name,
            'contactNumber' => $complaint->contact_number,
            'category' => $complaint->category,
            'description' => $complaint->description,
            'status' => $complaint->status,
            'dateSubmitted' => optional($complaint->date_submitted)->toDateString(),
            'evidencePath' => $complaint->evidence_path,
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
}
