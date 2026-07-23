<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class DispatchNotificationAlertsCommand extends Command
{
    protected $signature = 'notifications:dispatch-alerts
                            {--days=30 : Warranty expiry window in days}';

    protected $description = 'Kirim notifikasi garansi, maintenance jatuh tempo, dan peminjaman terlambat';

    public function handle(NotificationService $notifications): int
    {
        $days = max(1, (int) $this->option('days'));

        $warranty = $notifications->dispatchWarrantyExpiringAlerts($days);
        $maintenance = $notifications->dispatchMaintenanceDueAlerts();
        $overdue = $notifications->dispatchOverdueBorrowingAlerts();
        $total = $warranty + $maintenance + $overdue;

        $this->info("Notifikasi terkirim: garansi={$warranty}, maintenance={$maintenance}, terlambat={$overdue}, total={$total}");

        return self::SUCCESS;
    }
}
