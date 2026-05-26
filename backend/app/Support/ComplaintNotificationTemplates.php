<?php

namespace App\Support;

use App\Models\BarangaySetting;
use App\Models\Complaint;
use Illuminate\Support\Str;

class ComplaintNotificationTemplates
{
    public static function barangayName(?BarangaySetting $settings): string
    {
        $name = trim((string) ($settings?->barangay_name ?? ''));

        return $name !== '' ? $name : 'the Barangay';
    }

    public static function residentStatusEmailSubject(Complaint $complaint): string
    {
        return "Notice of Complaint Status Update — Ref. No. {$complaint->tracking_number}";
    }

    public static function residentStatusEmailBody(
        Complaint $complaint,
        string $previousStatus,
        string $barangayName,
        string $adminNotes = '',
        bool $statusChanged = true,
    ): string {
        $resident = trim((string) $complaint->resident_name);
        $salutation = $resident !== '' ? "Dear {$resident}," : 'Dear Sir/Madam,';

        $remarks = trim($adminNotes !== '' ? $adminNotes : (string) $complaint->admin_notes);
        $remarksBlock = $remarks !== ''
            ? "Barangay Remarks:\n{$remarks}"
            : 'Barangay Remarks: None at this time.';

        $intro = $statusChanged
            ? 'Please be informed that the status of your complaint filed with the Barangay through the PaBlotterMo Complaint Management System has been updated, as follows:'
            : 'Please be informed that the Barangay has recorded an update regarding your complaint filed through the PaBlotterMo Complaint Management System, as follows:';

        $statusLines = $statusChanged
            ? [
                "Previous Status: {$previousStatus}",
                "Current Status: {$complaint->status}",
            ]
            : [
                "Current Status: {$complaint->status}",
            ];

        return implode("\n", [
            $salutation,
            '',
            $intro,
            '',
            "Reference Number: {$complaint->tracking_number}",
            "Nature of Complaint: {$complaint->category}",
            ...$statusLines,
            $remarksBlock,
            '',
            'You may quote the reference number indicated above in any future correspondence with the Barangay.',
            '',
            'Thank you for your patience and cooperation.',
            '',
            'Respectfully yours,',
            'Barangay Complaint Management Office',
            $barangayName,
            'PaBlotterMo',
        ]);
    }

    public static function residentStatusSmsBody(
        Complaint $complaint,
        string $previousStatus,
        string $barangayName,
        string $adminNotes = '',
        bool $statusChanged = true,
    ): string {
        $remarks = trim($adminNotes !== '' ? $adminNotes : (string) $complaint->admin_notes);
        $message = $statusChanged
            ? "PaBlotterMo Advisory: Ref. {$complaint->tracking_number}. Your complaint status has been updated from {$previousStatus} to {$complaint->status}."
            : "PaBlotterMo Advisory: Ref. {$complaint->tracking_number}. An update has been posted regarding your complaint (status: {$complaint->status}).";

        if ($remarks !== '') {
            $message .= ' Remarks: '.Str::limit($remarks, 80, '…');
        }

        return "{$message} — {$barangayName}";
    }

    public static function adminNewComplaintSubject(Complaint $complaint): string
    {
        return "PaBlotterMo — New Complaint Received (Ref. No. {$complaint->tracking_number})";
    }

    /**
     * @param  non-empty-string  $contactChannel
     */
    public static function adminNewComplaintBody(
        Complaint $complaint,
        string $contactChannel,
        string $excerpt,
        string $adminDashboardUrl,
        string $barangayName,
    ): string {
        $submittedOn = $complaint->date_submitted?->format('F j, Y') ?? 'Not recorded';
        $channelLabel = match (strtolower($contactChannel)) {
            'email' => 'Electronic Mail (Email)',
            'phone' => 'Mobile Telephone (SMS)',
            default => ucfirst($contactChannel),
        };

        return implode("\n", [
            'Dear Barangay Official,',
            '',
            'This is an official notice that a new complaint has been lodged through the PaBlotterMo Complaint Management System and requires your attention.',
            '',
            'Complaint Particulars:',
            "Reference Number: {$complaint->tracking_number}",
            "Date Submitted: {$submittedOn}",
            "Category: {$complaint->category}",
            "Complainant: {$complaint->resident_name}",
            "Preferred Contact Channel: {$channelLabel}",
            '',
            'Summary of Complaint:',
            $excerpt !== '' ? $excerpt : '(No description provided.)',
            '',
            'Please log in to the administrative dashboard to review the complete record and take appropriate action:',
            $adminDashboardUrl,
            '',
            'This is an automated notification. Please do not reply directly to this message.',
            '',
            'Respectfully yours,',
            'PaBlotterMo System',
            $barangayName,
        ]);
    }
}
