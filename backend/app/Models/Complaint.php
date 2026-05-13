<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_UNDER_INVESTIGATION = 'Under Investigation';
    public const STATUS_RESOLVED = 'Resolved';

    public const CATEGORY_NOISE = 'Noise';
    public const CATEGORY_THEFT = 'Theft';
    public const CATEGORY_DOMESTIC = 'Domestic';
    public const CATEGORY_PROPERTY = 'Property';
    public const CATEGORY_OTHERS = 'Others';
    public const CONTACT_METHOD_PHONE = 'phone';
    public const CONTACT_METHOD_EMAIL = 'email';

    protected $fillable = [
        'tracking_number',
        'resident_name',
        'contact_number',
        'contact_method',
        'contact_value',
        'category',
        'description',
        'status',
        'date_submitted',
        'evidence_path',
        'evidence_paths',
        'assigned_officer',
        'admin_notes',
    ];

    protected $casts = [
        'date_submitted' => 'datetime',
        'evidence_paths' => 'array',
    ];
}
