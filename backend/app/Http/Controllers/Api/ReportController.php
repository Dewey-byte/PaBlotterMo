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

        $query = Complaint::query()->selectRaw('COUNT(*) as total');

        $query->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as status_pending', [Complaint::STATUS_PENDING]);
        $query->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as status_investigating', [Complaint::STATUS_UNDER_INVESTIGATION]);
        $query->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as status_resolved', [Complaint::STATUS_RESOLVED]);
        $query->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as status_rejected', [Complaint::STATUS_REJECTED]);

        foreach ($categories as $category) {
            $query->selectRaw(
                'SUM(CASE WHEN category = ? THEN 1 ELSE 0 END) as '.$this->categoryAggregateColumn($category),
                [$category]
            );
        }

        $row = $query->first();
        $total = (int) ($row->total ?? 0);

        $byCategory = collect($categories)->map(function (string $category) use ($row, $total): array {
            $column = $this->categoryAggregateColumn($category);
            $count = (int) ($row->{$column} ?? 0);

            return [
                'category' => $category,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        })->values();

        $statusOverview = [
            'pending' => (int) ($row->status_pending ?? 0),
            'investigating' => (int) ($row->status_investigating ?? 0),
            'resolved' => (int) ($row->status_resolved ?? 0),
            'rejected' => (int) ($row->status_rejected ?? 0),
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

    private function categoryAggregateColumn(string $category): string
    {
        return 'category_'.Str::snake($category);
    }

    private function exportCsv(): StreamedResponse
    {
        $fileName = 'complaints-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Tracking Number', 'Complainant', 'Contact', 'Category', 'Status', 'Date Submitted']);

            $this->streamReportRows(function (array $row) use ($output): void {
                fputcsv($output, $row);
            });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportExcel(): StreamedResponse
    {
        $fileName = 'complaints-report-'.now()->format('Ymd-His').'.xls';

        return response()->streamDownload(function (): void {
            echo '<table border="1"><thead><tr>';
            echo '<th>Tracking Number</th><th>Complainant</th><th>Contact</th><th>Category</th><th>Status</th><th>Date Submitted</th>';
            echo '</tr></thead><tbody>';

            $this->streamReportRows(function (array $row): void {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>'.e((string) $cell).'</td>';
                }
                echo '</tr>';
            });

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
        $rows = [];

        $this->streamReportRows(function (array $row) use (&$rows): void {
            $rows[] = $row;
        });

        return $rows;
    }

    /**
     * @param  callable(array<int, string>): void  $emit
     */
    private function streamReportRows(callable $emit): void
    {
        Complaint::query()
            ->select([
                'tracking_number',
                'resident_name',
                'contact_value',
                'contact_number',
                'category',
                'status',
                'date_submitted',
            ])
            ->latest('date_submitted')
            ->chunkById(200, function ($complaints) use ($emit): void {
                foreach ($complaints as $complaint) {
                    $emit([
                        $complaint->tracking_number,
                        $complaint->resident_name ?: 'Anonymous',
                        $complaint->contact_value ?? $complaint->contact_number,
                        $complaint->category,
                        $complaint->status,
                        optional($complaint->date_submitted)->toDateString() ?? '',
                    ]);
                }
            });
    }
}
