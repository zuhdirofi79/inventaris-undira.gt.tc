<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ScheduleOverlap
{
    /**
     * existing_start < requested_end AND existing_end > requested_start.
     */
    public static function apply(Builder $query, string $startColumn, string $endColumn, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->where($startColumn, '<', $end)
            ->where($endColumn, '>', $start);
    }
}
