<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_UNDER_INVESTIGATION = 'Under Investigation';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_REJECTED = 'Rejected';

    public const CATEGORY_NOISE = 'Noise';
    public const CATEGORY_THEFT = 'Theft';
    public const CATEGORY_DOMESTIC = 'Domestic';
    public const CATEGORY_PROPERTY = 'Property';
    public const CATEGORY_OTHERS = 'Others';
    public const CONTACT_METHOD_PHONE = 'phone';
    public const CONTACT_METHOD_EMAIL = 'email';

    protected $fillable = [
        'tracking_number',
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
    ];

    /**
     * Tolerant decoding: bad/legacy JSON must not crash list endpoints.
     *
     * @param  mixed  $value
     */
    public function getEvidencePathsAttribute($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : null;
    }

    /**
     * @param  mixed  $value
     */
    public function setEvidencePathsAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['evidence_paths'] = null;

            return;
        }

        $this->attributes['evidence_paths'] = json_encode(
            array_values(is_array($value) ? $value : []),
            JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * @return HasMany<ComplaintEvidence, $this>
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(ComplaintEvidence::class)->orderBy('sort_order');
    }
}

