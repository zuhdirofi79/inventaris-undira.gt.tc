<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ApprovalCancellationWindow
{
    public const HOURS = 24;

    public static function deadline(CarbonInterface $approvedAt): CarbonInterface
    {
        return $approvedAt->copy()->timezone((string) config('app.timezone'))->addHours(self::HOURS);
    }

    public static function isOpen(?CarbonInterface $approvedAt, ?CarbonInterface $moment = null): bool
    {
        if ($approvedAt === null) {
            return false;
        }

        $now = ($moment ?? now())->copy()->timezone((string) config('app.timezone'));

        return $now->lte(self::deadline($approvedAt));
    }

    public static function remainingLabel(?CarbonInterface $approvedAt, ?CarbonInterface $moment = null): string
    {
        if ($approvedAt === null) {
            return '—';
        }

        $deadline = self::deadline($approvedAt);
        $now = ($moment ?? now())->copy()->timezone((string) config('app.timezone'));
        if ($now->gt($deadline)) {
            return 'Batas pembatalan 24 jam telah berakhir';
        }

        return 'Batas pembatalan: '.$deadline->format('d/m/Y H:i');
    }

    public static function format(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        return Carbon::parse($value)->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
    }
}
