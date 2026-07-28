<?php

namespace App\Services;

use App\Models\Borrowing;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BorrowingApprovalNumberService
{
    /**
     * Generate unique APR-YYYYMMDD-000001 inside a DB transaction with row lock.
     */
    public function generate(?CarbonInterface $at = null): string
    {
        $at ??= now();
        $prefix = 'APR-'.$at->format('Ymd').'-';

        return DB::transaction(function () use ($prefix) {
            $last = Borrowing::query()
                ->where('approval_number', 'like', $prefix.'%')
                ->orderByDesc('approval_number')
                ->lockForUpdate()
                ->value('approval_number');

            $seq = 1;
            if (is_string($last) && preg_match('/-(\d{6})$/', $last, $matches) === 1) {
                $seq = ((int) $matches[1]) + 1;
            }

            return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
