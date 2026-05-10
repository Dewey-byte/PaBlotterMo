<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarangaySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->transform($this->getOrCreateSettings()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barangayName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'contactNumber' => ['required', 'string', 'max:50'],
            'notifyEmailNewComplaints' => ['required', 'boolean'],
            'notifySmsUrgentCases' => ['required', 'boolean'],
            'notifyDailySummaryReports' => ['required', 'boolean'],
        ]);

        $settings = $this->getOrCreateSettings();
        $settings->fill([
            'barangay_name' => $validated['barangayName'],
            'contact_email' => $validated['contactEmail'],
            'contact_number' => $validated['contactNumber'],
            'notify_email_new_complaints' => $validated['notifyEmailNewComplaints'],
            'notify_sms_urgent_cases' => $validated['notifySmsUrgentCases'],
            'notify_daily_summary_reports' => $validated['notifyDailySummaryReports'],
        ]);
        $settings->save();

        return response()->json([
            'message' => 'Settings saved successfully.',
            'settings' => $this->transform($settings),
        ]);
    }

    private function getOrCreateSettings(): BarangaySetting
    {
        return BarangaySetting::query()->firstOrCreate([], [
            'barangay_name' => 'Barangay Sample',
            'contact_email' => 'admin@barangay.gov.ph',
            'contact_number' => '(02) 8888-8888',
            'notify_email_new_complaints' => true,
            'notify_sms_urgent_cases' => true,
            'notify_daily_summary_reports' => false,
        ]);
    }

    private function transform(BarangaySetting $settings): array
    {
        return [
            'barangayName' => $settings->barangay_name,
            'contactEmail' => $settings->contact_email,
            'contactNumber' => $settings->contact_number,
            'notifyEmailNewComplaints' => (bool) $settings->notify_email_new_complaints,
            'notifySmsUrgentCases' => (bool) $settings->notify_sms_urgent_cases,
            'notifyDailySummaryReports' => (bool) $settings->notify_daily_summary_reports,
        ];
    }
}
