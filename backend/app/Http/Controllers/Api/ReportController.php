<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(string $format): StreamedResponse|Response|JsonResponse
    {
        $normalized = Str::lower($format);

        return match ($normalized) {
            'csv' => $this->exportCsv(),
            'excel' => $this->exportExcel(),
            'pdf' => $this->exportPdf(),
            default => response()->json(['message' => 'Unsupported export format.'], 422),
        };
    }

    private function exportCsv(): StreamedResponse
    {
        $fileName = 'complaints-report-'.now()->format('Ymd-His').'.csv';
        $rows = $this->reportRows();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Tracking Number', 'Complainant', 'Contact', 'Category', 'Status', 'Date Submitted']);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportExcel(): StreamedResponse
    {
        $fileName = 'complaints-report-'.now()->format('Ymd-His').'.xls';
        $rows = $this->reportRows();

        return response()->streamDownload(function () use ($rows): void {
            echo '<table border="1"><thead><tr>';
            echo '<th>Tracking Number</th><th>Complainant</th><th>Contact</th><th>Category</th><th>Status</th><th>Date Submitted</th>';
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>'.e((string) $cell).'</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function exportPdf(): Response
    {
        $rows = $this->reportRows();
        $generatedAt = now()->toDayDateTimeString();

        $html = '<h2 style="margin-bottom:8px;">PaBlotterMo Complaint Report</h2>';
        $html .= '<p style="margin-top:0;">Generated: '.e($generatedAt).'</p>';
        $html .= '<table width="100%" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr style="background:#f1f5f9;">';
        $html .= '<th>Tracking Number</th><th>Complainant</th><th>Contact</th><th>Category</th><th>Status</th><th>Date Submitted</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.e((string) $cell).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return Pdf::loadHTML($html)->download('complaints-report-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function reportRows(): array
    {
        return Complaint::query()
            ->latest('date_submitted')
            ->get()
            ->map(function (Complaint $complaint): array {
                return [
                    $complaint->tracking_number,
                    $complaint->resident_name ?: 'Anonymous',
                    $complaint->contact_value ?? $complaint->contact_number,
                    $complaint->category,
                    $complaint->status,
                    optional($complaint->date_submitted)->toDateString() ?? '',
                ];
            })
            ->all();
    }
}
