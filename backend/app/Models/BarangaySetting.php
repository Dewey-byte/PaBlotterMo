<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangaySetting extends Model
{
    protected $fillable = [
        'barangay_name',
        'contact_email',
        'contact_number',
        'notify_email_new_complaints',
        'notify_sms_urgent_cases',
        'notify_daily_summary_reports',
    ];

    protected $casts = [
        'notify_email_new_complaints' => 'boolean',
        'notify_sms_urgent_cases' => 'boolean',
        'notify_daily_summary_reports' => 'boolean',
    ];
}
