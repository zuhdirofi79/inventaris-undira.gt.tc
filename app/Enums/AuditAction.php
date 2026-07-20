<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Import = 'import';
    case Export = 'export';
    case Download = 'download';
    case ScanQr = 'scan_qr';
    case Restore = 'restore';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::Create => 'Tambah',
            self::Update => 'Ubah',
            self::Delete => 'Hapus',
            self::Import => 'Import',
            self::Export => 'Export',
            self::Download => 'Download',
            self::ScanQr => 'Scan Barcode/QR',
            self::Restore => 'Restore',
        };
    }
}
