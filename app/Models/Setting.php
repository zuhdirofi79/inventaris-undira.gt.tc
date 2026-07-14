<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(static::cacheKey($key), function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $group = null): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
                'group' => $group,
            ], fn ($item) => $item !== null)
        );

        Cache::forget(static::cacheKey($key));

        return $setting;
    }

    public static function forget(string $key): bool
    {
        Cache::forget(static::cacheKey($key));

        return (bool) static::query()->where('key', $key)->delete();
    }

    protected static function cacheKey(string $key): string
    {
        return 'setting.'.$key;
    }
}
