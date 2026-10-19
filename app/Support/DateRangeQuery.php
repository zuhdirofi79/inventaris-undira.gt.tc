<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DateRangeQuery
{
    /**
     * @return array{date_from: string|null, date_to: string|null}
     *
     * @throws ValidationException
     */
    public static function validated(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ], [
            'date_from.date' => 'Dari Tanggal tidak valid.',
            'date_to.date' => 'Sampai Tanggal tidak valid.',
            'date_to.after_or_equal' => 'Sampai Tanggal harus sama dengan atau setelah Dari Tanggal.',
        ]);

        return [
            'date_from' => filled($data['date_from'] ?? null) ? (string) $data['date_from'] : null,
            'date_to' => filled($data['date_to'] ?? null) ? (string) $data['date_to'] : null,
        ];
    }

    /**
     * Filter by approval decision timestamp (approved_at, rejected_at, or cancelled_at),
     * matching Riwayat Approval Inventaris semantics.
     *
     * @param  Builder<*>  $query
     */
    public static function applyDecisionDates(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom === null && $dateTo === null) {
            return $query;
        }

        return $query->where(function ($outer) use ($dateFrom, $dateTo) {
            $outer->where(function ($approved) use ($dateFrom, $dateTo) {
                $approved->whereNotNull('approved_at');
                if ($dateFrom !== null) {
                    $approved->whereDate('approved_at', '>=', $dateFrom);
                }
                if ($dateTo !== null) {
                    $approved->whereDate('approved_at', '<=', $dateTo);
                }
            })->orWhere(function ($rejected) use ($dateFrom, $dateTo) {
                $rejected->whereNotNull('rejected_at');
                if ($dateFrom !== null) {
                    $rejected->whereDate('rejected_at', '>=', $dateFrom);
                }
                if ($dateTo !== null) {
                    $rejected->whereDate('rejected_at', '<=', $dateTo);
                }
            })->orWhere(function ($cancelled) use ($dateFrom, $dateTo) {
                $cancelled->whereNotNull('cancelled_at');
                if ($dateFrom !== null) {
                    $cancelled->whereDate('cancelled_at', '>=', $dateFrom);
                }
                if ($dateTo !== null) {
                    $cancelled->whereDate('cancelled_at', '<=', $dateTo);
                }
            });
        });
    }

    /**
     * Inclusive filter on a schedule/start datetime column (borrow_date / starts_at).
     *
     * @param  Builder<*>  $query
     */
    public static function applyStartDates(Builder $query, string $column, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom !== null) {
            $query->whereDate($column, '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate($column, '<=', $dateTo);
        }

        return $query;
    }
}
