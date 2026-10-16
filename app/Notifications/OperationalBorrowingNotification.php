<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Operational alerts for Driver / OB / Teknisi (database + email).
 *
 * @phpstan-type Payload array{
 *     type: string,
 *     event: string,
 *     title: string,
 *     message: string,
 *     kind: string,
 *     reference_id: int,
 *     reference_key: string,
 *     url?: string|null,
 *     subject?: string|null,
 *     subtitle?: string|null,
 *     summary?: string|null,
 *     mail_subject?: string|null,
 *     mail_lines?: list<string>
 * }
 */
class OperationalBorrowingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Payload  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $email = is_object($notifiable) ? trim((string) ($notifiable->email ?? '')) : '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $data = $this->payload;
        $data['url'] = $data['url'] ?? route('borrowing-records.index', [], false);

        return $data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = (string) ($this->payload['mail_subject'] ?? $this->payload['title'] ?? 'Notifikasi Peminjaman');
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Halo '.($notifiable->name ?? 'Pengguna'));

        foreach ($this->payload['mail_lines'] ?? [$this->payload['message'] ?? ''] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $mail->line($line);
            }
        }

        $url = (string) ($this->payload['url'] ?? '');
        if ($url !== '') {
            $mail->action('Lihat Detail', url($url));
        }

        return $mail->line('Pesan ini dikirim otomatis oleh LOADOUT / Inventaris UNDIRA.');
    }
}
