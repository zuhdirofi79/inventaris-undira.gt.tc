<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
        'reason',
        'affected_count',
    ];

    protected $appends = [
        'browser',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'affected_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function browser(): Attribute
    {
        return Attribute::get(fn () => $this->parseUserAgent()['browser']);
    }

    protected function device(): Attribute
    {
        return Attribute::get(fn () => $this->parseUserAgent()['device']);
    }

    /**
     * @return array{browser: string, device: string}
     */
    protected function parseUserAgent(): array
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return ['browser' => '-', 'device' => '-'];
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            str_contains($ua, 'Opera') || str_contains($ua, 'OPR/') => 'Opera',
            default => 'Lainnya',
        };

        $device = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mobile') => 'Mobile',
            str_contains($ua, 'Windows') => 'Desktop Windows',
            str_contains($ua, 'Macintosh') => 'Desktop Mac',
            str_contains($ua, 'Linux') => 'Desktop Linux',
            default => 'Unknown',
        };

        return ['browser' => $browser, 'device' => $device];
    }
}
