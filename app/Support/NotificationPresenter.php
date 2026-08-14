<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    /**
     * Shape a database notification for the navbar dropdown (display only).
     *
     * @return array{
     *     id: string,
     *     unread: bool,
     *     title: string,
     *     subject: ?string,
     *     subtitle: ?string,
     *     summary: ?string,
     *     message: string,
     *     time_ago: string,
     *     icon: string,
     *     url: string,
     *     type: string,
     *     event: string
     * }
     */
    public static function forDropdown(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = trim((string) ($data['title'] ?? 'Notifikasi'));
        $message = trim((string) ($data['message'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        $event = (string) ($data['event'] ?? '');
        $url = (string) ($data['url'] ?? route('notifications.index'));

        $subject = self::nullableString($data['wo_number'] ?? $data['subject'] ?? null);
        $subtitle = self::nullableString($data['asset_name'] ?? $data['subtitle'] ?? null);
        $code = self::nullableString($data['asset_code'] ?? null);
        $campus = self::nullableString($data['campus'] ?? null);
        $summary = self::nullableString($data['summary'] ?? null);

        if ($subject === null && preg_match('/\b((?:MNT|WO)-[\w-]+)\b/i', $message, $m)) {
            $subject = strtoupper($m[1]);
        }

        if ($subtitle === null && preg_match('/untuk\s+(.+?)(?:\s+\(([A-Z0-9.\-\/]+)\))?(?:\s+menunggu|\s+telah|\s+dikembalikan|\.|$)/iu', $message, $m)) {
            $subtitle = trim($m[1]);
            if ($code === null && ! empty($m[2])) {
                $code = $m[2];
            }
        }

        if ($subtitle !== null && $code !== null && ! str_contains($subtitle, $code)) {
            $subtitle = $subtitle.' ('.$code.')';
        }

        if ($summary === null && $campus !== null) {
            $summary = $campus;
        }

        if ($summary === null) {
            $summary = self::summaryFromEvent($event, $message, $subject, $subtitle);
        }

        if ($subject !== null && str_starts_with(strtoupper($subject), 'MNT-') && ! str_starts_with(strtoupper($subject), 'WO ')) {
            $subject = 'WO '.$subject;
        }

        return [
            'id' => (string) $notification->id,
            'unread' => $notification->read_at === null,
            'title' => $title !== '' ? $title : 'Notifikasi',
            'subject' => $subject,
            'subtitle' => $subtitle,
            'summary' => $summary,
            'message' => $message,
            'time_ago' => $notification->created_at?->diffForHumans() ?? '',
            'icon' => self::iconFor($type, $title),
            'url' => $url !== '' ? $url : route('notifications.index'),
            'type' => $type,
            'event' => $event,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function summaryFromEvent(string $event, string $message, ?string $subject, ?string $subtitle): ?string
    {
        $fromEvent = match ($event) {
            'submitted' => 'Menunggu persetujuan admin',
            'approved' => 'Disetujui',
            'rejected', 'revision' => 'Perlu revisi',
            'completed' => 'Selesai',
            default => null,
        };

        if ($fromEvent !== null) {
            return $fromEvent;
        }

        if ($message === '') {
            return null;
        }

        // Avoid duplicating a long single-line message when structured lines exist.
        if ($subject !== null || $subtitle !== null) {
            return null;
        }

        return $message;
    }

    private static function iconFor(string $type, string $title): string
    {
        $haystack = strtolower($type.' '.$title);

        return match (true) {
            str_contains($haystack, 'maintenance') || str_contains($haystack, 'pemeliharaan') || str_contains($haystack, 'perbaikan') => 'fas fa-wrench',
            str_contains($haystack, 'borrow') || str_contains($haystack, 'peminjaman') => 'fas fa-handshake',
            str_contains($haystack, 'stock_opname') || str_contains($haystack, 'opname') => 'fas fa-clipboard-list',
            str_contains($haystack, 'warranty') || str_contains($haystack, 'garansi') => 'fas fa-shield-alt',
            str_contains($haystack, 'deletion') || str_contains($haystack, 'hapus') => 'fas fa-trash-alt',
            default => 'fas fa-bell',
        };
    }
}
