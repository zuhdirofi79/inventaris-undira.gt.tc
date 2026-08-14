<?php

namespace App\Notifications;

use App\Models\MaintenanceSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Preventive schedule reminder.
 * Channel today: database (Notification Center / bell).
 * Email is prepared via toMail() — enable by adding 'mail' to via() when SMTP is ready.
 */
class MaintenanceScheduleReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MaintenanceSchedule $schedule,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Future: return ['database', 'mail'] when mail transport is configured.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->schedule->loadMissing([
            'inventoryItem.room.floor.building.campus',
        ]);

        $item = $this->schedule->inventoryItem;
        $campus = $item?->room?->floor?->building?->campus?->name;
        $date = $this->schedule->next_maintenance_date?->format('d/m/Y');
        $typeLabel = $this->schedule->maintenance_type?->label() ?? 'Preventif';
        $overdue = $this->schedule->isOverdue();
        $locationBit = $campus ? " · Lokasi: {$campus}" : '';

        return [
            'type' => $overdue ? 'maintenance_schedule_overdue' : 'maintenance_schedule_due',
            'title' => $overdue ? 'Pemeliharaan Terlambat' : 'Pengingat Pemeliharaan Preventif',
            'message' => $overdue
                ? "Jadwal {$item?->name} ({$item?->inventory_number}) terlambat sejak {$date}. Jenis: {$typeLabel}{$locationBit}."
                : "Jadwal preventive maintenance akan jatuh tempo. Inventaris: {$item?->name} ({$item?->inventory_number}). Lokasi: ".($campus ?: '—').". Jadwal: {$date}. Jenis: {$typeLabel}.",
            'maintenance_schedule_id' => $this->schedule->id,
            'inventory_item_id' => $this->schedule->inventory_item_id,
            'asset_name' => $item?->name,
            'asset_code' => $item?->inventory_number,
            'campus' => $campus,
            'maintenance_type' => $this->schedule->maintenance_type?->value,
            'maintenance_type_label' => $typeLabel,
            'next_maintenance_date' => $this->schedule->next_maintenance_date?->toDateString(),
            'responsible_pic' => $this->schedule->responsible_pic,
            'summary' => $campus
                ? ($overdue ? "Terlambat · {$campus}" : "Jatuh tempo {$date} · {$campus}")
                : ($overdue ? 'Terlambat' : "Jatuh tempo {$date}"),
            'url' => route('maintenance-schedules.show', $this->schedule, false),
        ];
    }

    /**
     * Prepared for future email delivery (not active until 'mail' is in via()).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->schedule->loadMissing([
            'inventoryItem.room.floor.building.campus',
        ]);

        $item = $this->schedule->inventoryItem;
        $campus = $item?->room?->floor?->building?->campus?->name;
        $date = $this->schedule->next_maintenance_date?->format('d/m/Y') ?? '-';
        $typeLabel = $this->schedule->maintenance_type?->label() ?? 'Preventif';
        $overdue = $this->schedule->isOverdue();

        return (new MailMessage)
            ->subject($overdue ? '[UNDIRA] Pemeliharaan terlambat' : '[UNDIRA] Pengingat pemeliharaan preventif')
            ->greeting('Halo '.$notifiable->name)
            ->line($overdue
                ? "Jadwal pemeliharaan untuk {$item?->name} ({$item?->inventory_number}) terlambat sejak {$date}."
                : "Jadwal pemeliharaan untuk {$item?->name} ({$item?->inventory_number}) jatuh tempo pada {$date}.")
            ->line('Lokasi kampus: '.($campus ?: '—'))
            ->line('Jenis: '.$typeLabel)
            ->line('PIC: '.($this->schedule->responsible_pic ?: '—'))
            ->line('Interval: '.$this->schedule->intervalLabel())
            ->action('Lihat Jadwal', url(route('maintenance-schedules.show', $this->schedule, false)))
            ->line('Pesan ini dikirim otomatis oleh Inventaris UNDIRA.');
    }
}
