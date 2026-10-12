<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Template resmi diletakkan di disk local:
 * storage/app/templates/room-borrowings/{basename}.pdf|docx|xlsx
 * Tombol unduh hanya muncul jika file itu benar-benar ada.
 */
class RoomBorrowingTemplates
{
    public const DISK = 'local';

    /**
     * @var array<string, array{basename: string, label: string}>
     */
    public const KINDS = [
        'approval' => [
            'basename' => 'persetujuan-pimpinan',
            'label' => 'Download Template Dokumen',
        ],
        'layout' => [
            'basename' => 'layout-acara',
            'label' => 'Download Template Layout Acara',
        ],
        'participants' => [
            'basename' => 'daftar-peserta',
            'label' => 'Download Template Daftar Peserta',
        ],
    ];

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['pdf', 'docx', 'xlsx'];

    public function find(string $kind): ?string
    {
        $meta = self::KINDS[$kind] ?? null;
        if ($meta === null) {
            return null;
        }

        foreach (self::EXTENSIONS as $extension) {
            $path = 'templates/room-borrowings/'.$meta['basename'].'.'.$extension;
            if (Storage::disk(self::DISK)->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, url: string}>
     */
    public function links(): array
    {
        $links = [];
        foreach (self::KINDS as $kind => $meta) {
            if ($this->find($kind) === null) {
                continue;
            }

            $links[$kind] = [
                'label' => $meta['label'],
                'url' => route('room-borrowings.templates', $kind),
            ];
        }

        return $links;
    }
}
