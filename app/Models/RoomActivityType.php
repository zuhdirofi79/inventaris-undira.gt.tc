<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RoomActivityType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function legacyDefaults(): array
    {
        return [
            ['code' => 'rapat', 'name' => 'Rapat'],
            ['code' => 'akademik', 'name' => 'Kegiatan Akademik'],
            ['code' => 'seminar', 'name' => 'Seminar'],
            ['code' => 'lainnya', 'name' => 'Kegiatan Lainnya'],
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(RoomBorrowing::class, 'activity_type', 'code');
    }

    public function isUsed(): bool
    {
        return $this->borrowings()->exists();
    }

    public static function labelFor(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '—';
        }

        $name = static::query()->where('code', $code)->value('name');
        if (filled($name)) {
            return (string) $name;
        }

        foreach (static::legacyDefaults() as $row) {
            if ($row['code'] === $code) {
                return $row['name'];
            }
        }

        return $code;
    }

    public static function makeUniqueCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($name), '-');
        if ($base === '') {
            $base = 'kegiatan';
        }
        $base = Str::limit($base, 36, '');

        $code = $base;
        $suffix = 2;
        while (
            static::query()
                ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->where('code', $code)
                ->exists()
        ) {
            $code = Str::limit($base, 32, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
