<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case ForceDelete = 'force_delete';
    case Import = 'import';
    case Export = 'export';
    case Download = 'download';
    case ScanQr = 'scan_qr';
    case GenerateQr = 'generate_qr';
    case Restore = 'restore';
    case ResetPassword = 'reset_password';
    case VerifyEmail = 'verify_email';
    case Mutate = 'mutate';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::Create => 'Tambah',
            self::Update => 'Ubah',
            self::Delete => 'Hapus',
            self::ForceDelete => 'Hapus Permanen',
            self::Import => 'Import',
            self::Export => 'Export',
            self::Download => 'Download',
            self::ScanQr => 'Scan QR Code',
            self::GenerateQr => 'Generate QR Code',
            self::Restore => 'Restore',
            self::ResetPassword => 'Reset Password',
            self::VerifyEmail => 'Verifikasi Email',
            self::Mutate => 'Mutasi Inventaris',
        };
    }
}
