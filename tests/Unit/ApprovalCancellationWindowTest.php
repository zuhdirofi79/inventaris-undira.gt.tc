<?php

namespace Tests\Unit;

use App\Support\ApprovalCancellationWindow;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApprovalCancellationWindowTest extends TestCase
{
    public function test_window_is_open_before_and_at_exact_24_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'));
        $approvedAt = Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta');

        $this->assertTrue(ApprovalCancellationWindow::isOpen($approvedAt));

        Carbon::setTestNow(Carbon::parse('2026-10-18 09:48:00', 'Asia/Jakarta'));
        $this->assertTrue(ApprovalCancellationWindow::isOpen($approvedAt));

        Carbon::setTestNow(Carbon::parse('2026-10-18 09:48:01', 'Asia/Jakarta'));
        $this->assertFalse(ApprovalCancellationWindow::isOpen($approvedAt));

        Carbon::setTestNow();
    }

    public function test_deadline_uses_actual_duration_not_calendar_day(): void
    {
        $approvedAt = Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta');
        $deadline = ApprovalCancellationWindow::deadline($approvedAt);

        $this->assertSame('2026-10-18 09:48:00', $deadline->format('Y-m-d H:i:s'));
    }
}
