<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function overview(): JsonResponse
    {
        $categories = [
            Complaint::CATEGORY_NOISE,
            Complaint::CATEGORY_THEFT,
            Complaint::CATEGORY_DOMESTIC,
            Complaint::CATEGORY_PROPERTY,
            Complaint::CATEGORY_OTHERS,
        ];

        $total = Complaint::query()->count();

        $byCategory = collect($categories)->map(function (string $category) use ($total): array {
            $count = Complaint::query()->where('category', $category)->count();

            return [
                'category' => $category,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        })->values();

        $statusOverview = [
            'pending' => Complaint::query()->where('status', Complaint::STATUS_PENDING)->count(),
            'investigating' => Complaint::query()->where('status', Complaint::STATUS_UNDER_INVESTIGATION)->count(),
            'resolved' => Complaint::query()->where('status', Complaint::STATUS_RESOLVED)->count(),
        ];

        return response()->json([
            'total' => $total,
            'byCategory' => $byCategory,
            'statusOverview' => $statusOverview,
            'generatedAt' => now()->toISOString(),
        ]);
    }
}
