<?php

namespace App\Models\Concerns;

use App\Support\ApprovalCancellationWindow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TracksApprovalCancellation
{
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by');
    }

    public function approvalCancelDeadline(): ?CarbonInterface
    {
        if ($this->approved_at === null) {
            return null;
        }

        return ApprovalCancellationWindow::deadline($this->approved_at);
    }

    public function isWithinApprovalCancelWindow(?CarbonInterface $moment = null): bool
    {
        return ApprovalCancellationWindow::isOpen($this->approved_at, $moment);
    }

    public function decisionTimestamp(): ?CarbonInterface
    {
        return $this->cancelled_at ?? $this->approved_at ?? $this->rejected_at;
    }
}
