<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\InventoryCodeService;
use Illuminate\Support\Facades\Storage;

/**
 * Canonical application settings keys (DB + UI).
 * Internal keys (e.g. primary_super_admin) stay out of the Settings form.
 */
final class AppSettings
{
    public const GROUP_APP = 'app';

    public const GROUP_INVENTORY = 'inventory';

    public const KEY_APP_NAME = 'app.name';

    public const KEY_UNIVERSITY_NAME = 'app.university_name';

    public const KEY_FOOTER_TEXT = 'app.footer_text';

    public const KEY_LOGO_PATH = 'app.logo_path';

    public const KEY_FAVICON_PATH = 'app.favicon_path';

    public const KEY_NUMBER_FORMAT = 'inventory.number_format';

    public const KEY_SEQUENCE_PADDING = 'inventory.sequence_padding';

    public const KEY_SCAN_BASE_URL = 'inventory.scan_base_url';

    /** Official format — enforced in InventoryCodeService, not user-editable. */
    public const NUMBER_FORMAT = '{campus}-{procurement}-{year}-{category}-{sub_category}-{sequence}';

    public const NUMBER_FORMAT_EXAMPLE = 'CBB-HIBAH-26-EL-LPT-000001';

    /**
     * Keys that may be written from the Settings form.
     *
     * @return list<string>
     */
    public static function editableKeys(): array
    {
        return [
            self::KEY_APP_NAME,
            self::KEY_UNIVERSITY_NAME,
            self::KEY_FOOTER_TEXT,
            self::KEY_SEQUENCE_PADDING,
            self::KEY_SCAN_BASE_URL,
        ];
    }

    /**
     * Seed / ensure defaults without overwriting user values.
     */
    public static function ensureDefaults(): void
    {
        $defaults = [
            self::KEY_APP_NAME => ['Inventaris UNDIRA', self::GROUP_APP],
            self::KEY_UNIVERSITY_NAME => ['Universitas Dian Nusantara', self::GROUP_APP],
            self::KEY_FOOTER_TEXT => ['© 2026 Biro PPPA. Universitas Dian Nusantara', self::GROUP_APP],
            self::KEY_LOGO_PATH => ['', self::GROUP_APP],
            self::KEY_FAVICON_PATH => ['', self::GROUP_APP],
            self::KEY_NUMBER_FORMAT => [self::NUMBER_FORMAT, self::GROUP_INVENTORY],
            self::KEY_SEQUENCE_PADDING => [(string) InventoryCodeService::DEFAULT_PADDING, self::GROUP_INVENTORY],
            self::KEY_SCAN_BASE_URL => ['', self::GROUP_INVENTORY],
        ];

        foreach ($defaults as $key => [$value, $group]) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::set($key, $value, $group);
            }
        }

        // Keep format row in sync with the code-enforced template (read-only in UI).
        Setting::set(self::KEY_NUMBER_FORMAT, self::NUMBER_FORMAT, self::GROUP_INVENTORY);
    }

    public static function appName(): string
    {
        $value = trim((string) Setting::get(self::KEY_APP_NAME, ''));

        return $value !== '' ? $value : (string) config('app.name', 'Inventaris UNDIRA');
    }

    public static function universityName(): string
    {
        $value = trim((string) Setting::get(self::KEY_UNIVERSITY_NAME, ''));

        return $value !== '' ? $value : 'Universitas Dian Nusantara';
    }

    public static function footerText(): string
    {
        $value = trim((string) Setting::get(self::KEY_FOOTER_TEXT, ''));

        return $value !== '' ? $value : ('© '.date('Y').' Biro PPPA. '.self::universityName());
    }

    /**
     * Public URL for sidebar / branding logo (falls back to bundled asset).
     */
    public static function logoUrl(): string
    {
        $path = trim((string) Setting::get(self::KEY_LOGO_PATH, ''));
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset('images/LOGO UNDIRA.png');
    }

    /**
     * Public URL for custom favicon, or null to use bundled defaults.
     */
    public static function faviconUrl(): ?string
    {
        $path = trim((string) Setting::get(self::KEY_FAVICON_PATH, ''));
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    /**
     * Scan base for QR/UI links. Empty setting → config('app.url').
     */
    public static function scanBaseUrl(): string
    {
        $base = rtrim(trim((string) Setting::get(self::KEY_SCAN_BASE_URL, '')), '/');
        if ($base !== '') {
            return $base;
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function sequencePadding(): int
    {
        $padding = (int) Setting::get(self::KEY_SEQUENCE_PADDING, InventoryCodeService::DEFAULT_PADDING);

        return $padding < 1 ? InventoryCodeService::DEFAULT_PADDING : min($padding, 12);
    }
}
