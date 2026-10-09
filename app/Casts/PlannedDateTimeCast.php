<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Planned borrow/return instants.
 * Date-only strings stay date-only so legacy rows are not rewritten as midnight.
 * Values that include a time are stored as Y-m-d H:i:s in the application timezone.
 */
class PlannedDateTimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, $this->timezone());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        $instant = $value instanceof CarbonInterface
            ? CarbonImmutable::parse($value)->timezone($this->timezone())
            : CarbonImmutable::parse((string) $value, $this->timezone());

        return $instant->format('Y-m-d H:i:s');
    }

    protected function timezone(): string
    {
        return (string) config('app.timezone');
    }
}
