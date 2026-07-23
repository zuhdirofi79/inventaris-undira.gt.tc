<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class DispatchNotificationAlertsCommand extends Command
{
    protected $signature = 'notifications:dispatch-alerts
                            {--days=30 : Warranty expiry window in days}';

    protected $description = 'Kirim notifikasi garansi, jadwal/WO maintenance, dan peminjaman terlambat';

    public function handle(NotificationService $notifications): int
    {
        $days = max(1, (int) $this->option('days'));

        $warranty = $notifications->dispatchWarrantyExpiringAlerts($days);
        $maintenance = $notifications->dispatchMaintenanceDueAlerts();
        $schedules = $notifications->dispatchMaintenanceScheduleAlerts();
        $overdue = $notifications->dispatchOverdueBorrowingAlerts();
        $total = $warranty + $maintenance + $schedules + $overdue;

        $this->info("Notifikasi terkirim: garansi={$warranty}, wo={$maintenance}, jadwal={$schedules}, terlambat={$overdue}, total={$total}");

        return self::SUCCESS;
    }
}
