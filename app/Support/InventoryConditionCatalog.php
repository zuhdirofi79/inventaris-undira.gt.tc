<?php

namespace App\Support;

use App\Enums\InventoryCondition;
use App\Models\InventoryConditionMaster;
use Illuminate\Support\Collection;

/**
 * Hub Master Data → Kondisi Aset is the only list of asset conditions.
 * inventory_items.condition stores the master code. Older rows may still hold the legacy enum value.
 */
final class InventoryConditionCatalog
{
    /** @var Collection<int, InventoryConditionMaster>|null */
    private static ?Collection $rows = null;

    public static function flush(): void
    {
        self::$rows = null;
    }

    /**
     * Active master options for create/edit. The current stored value stays selectable
     * when it is inactive or still a legacy enum value.
     *
     * @return list<InventoryConditionValue>
     */
    public static function activeOptions(?string $current = null): array
    {
        $options = [];
        $seen = [];

        foreach (self::rows() as $row) {
            if (! $row->is_active) {
                continue;
            }
            $options[] = new InventoryConditionValue($row->code);
            $seen[strtoupper($row->code)] = true;
        }

        $current = trim((string) $current);
        if ($current !== '' && ! isset($seen[strtoupper($current)]) && ! self::matchesMasterCode($current)) {
            array_unshift($options, new InventoryConditionValue($current));
        } elseif ($current !== '' && ! isset($seen[strtoupper($current)])) {
            array_unshift($options, new InventoryConditionValue(self::canonicalCode($current) ?? $current));
        }

        return $options;
    }

    /**
     * All master rows for filters, including inactive ones that old inventory may still use.
     *
     * @return list<InventoryConditionValue>
     */
    public static function filterOptions(): array
    {
        return self::rows()
            ->map(fn (InventoryConditionMaster $row) => new InventoryConditionValue($row->code))
            ->all();
    }

    public static function label(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '—';
        }

        $master = self::findByCode($stored);
        if ($master) {
            return $master->name;
        }

        return InventoryCondition::tryFrom(strtolower($stored))?->label() ?? $stored;
    }

    public static function tone(?string $stored): string
    {
        $legacy = InventoryCondition::tryFrom(strtolower(trim((string) $stored)));
        if ($legacy) {
            return $legacy->badgeTone();
        }

        $master = self::findByCode((string) $stored);
        $haystack = strtolower(trim(($master?->code ?? '').' '.($master?->name ?? $stored)));

        if (str_contains($haystack, 'rusak')) {
            return 'damaged';
        }

        if (str_contains($haystack, 'layak') || str_contains($haystack, 'cukup')) {
            return 'fair';
        }

        return 'good';
    }

    public static function isSelectable(string $input, ?string $current = null): bool
    {
        $input = trim($input);
        if ($input === '') {
            return false;
        }

        if (self::canonicalActiveCode($input) !== null) {
            return true;
        }

        $current = trim((string) $current);

        return $current !== '' && strcasecmp($input, $current) === 0;
    }

    public static function canonicalActiveCode(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        foreach (self::rows() as $row) {
            if (! $row->is_active) {
                continue;
            }
            if (strcasecmp($row->code, $input) === 0 || strcasecmp($row->name, $input) === 0) {
                return $row->code;
            }
        }

        return null;
    }

    /**
     * Values that should match a filter selection, including the historical enum written before master codes.
     *
     * @return list<string>
     */
    public static function filterValues(string $input): array
    {
        $input = trim($input);
        $values = [$input];
        $master = self::findByCode($input) ?? self::findByName($input);

        if ($master) {
            $values[] = $master->code;
            $values[] = strtolower($master->code);
            $legacy = self::legacyStoredValue($master);
            if ($legacy) {
                $values[] = $legacy;
            }
        }

        return array_values(array_unique(array_filter($values, fn ($value) => $value !== '')));
    }

    /**
     * @return list<string>
     */
    public static function equivalentStoredValues(InventoryConditionMaster $master): array
    {
        $values = [$master->code, strtolower($master->code), strtoupper($master->code)];
        $legacy = self::legacyStoredValue($master);
        if ($legacy) {
            $values[] = $legacy;
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<string>
     */
    public static function damagedStoredValues(): array
    {
        $values = [
            InventoryCondition::RusakRingan->value,
            InventoryCondition::RusakBerat->value,
        ];

        foreach (self::rows() as $row) {
            if (self::tone($row->code) !== 'damaged') {
                continue;
            }
            $values[] = $row->code;
            $values[] = strtolower($row->code);
        }

        return array_values(array_unique($values));
    }

    public static function isDamagedStored(?string $stored): bool
    {
        if ($stored === null || trim($stored) === '') {
            return false;
        }

        return in_array($stored, self::damagedStoredValues(), true)
            || in_array(strtolower($stored), self::damagedStoredValues(), true);
    }

    /**
     * @param  Collection<string|int, mixed>|array<string|int, mixed>  $counts
     * @return list<array{code: string, label: string, total: int, tone: string}>
     */
    public static function cards(Collection|array $counts): array
    {
        $counts = $counts instanceof Collection ? $counts : collect($counts);
        $cards = [];
        $used = [];

        foreach (self::rows() as $row) {
            $total = 0;
            foreach (self::equivalentStoredValues($row) as $value) {
                $total += (int) ($counts[$value] ?? 0);
                $used[$value] = true;
            }

            if (! $row->is_active && $total === 0) {
                continue;
            }

            $cards[] = [
                'code' => $row->code,
                'label' => $row->name,
                'total' => $total,
                'tone' => self::tone($row->code),
            ];
        }

        foreach ($counts as $key => $total) {
            $key = (string) $key;
            if ($key === '' || isset($used[$key]) || (int) $total < 1) {
                continue;
            }
            $cards[] = [
                'code' => $key,
                'label' => self::label($key),
                'total' => (int) $total,
                'tone' => self::tone($key),
            ];
        }

        return $cards;
    }

    public static function legacyStoredValue(InventoryConditionMaster $master): ?string
    {
        $code = strtolower(trim($master->code));
        $name = strtolower(trim($master->name));

        if ($enum = InventoryCondition::tryFrom($code)) {
            return $enum->value;
        }

        return match (true) {
            in_array($code, ['baik', 'bagus', 'good'], true) || str_contains($name, 'baik') || str_contains($name, 'bagus') => InventoryCondition::Bagus->value,
            in_array($code, ['baru', 'new'], true) || str_contains($name, 'baru') => InventoryCondition::Baru->value,
            in_array($code, ['layak', 'cukup'], true) || str_contains($name, 'layak') || str_contains($name, 'cukup') => InventoryCondition::Cukup->value,
            str_contains($code, 'ringan') || str_contains($name, 'ringan') => InventoryCondition::RusakRingan->value,
            in_array($code, ['rusak', 'rusak_berat', 'berat'], true) || str_contains($name, 'rusak') => InventoryCondition::RusakBerat->value,
            default => null,
        };
    }

    /**
     * @return Collection<int, InventoryConditionMaster>
     */
    private static function rows(): Collection
    {
        return self::$rows ??= InventoryConditionMaster::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active', 'sort_order']);
    }

    private static function findByCode(string $input): ?InventoryConditionMaster
    {
        $input = trim($input);
        foreach (self::rows() as $row) {
            if (strcasecmp($row->code, $input) === 0) {
                return $row;
            }
        }

        return null;
    }

    private static function findByName(string $input): ?InventoryConditionMaster
    {
        $input = trim($input);
        foreach (self::rows() as $row) {
            if (strcasecmp($row->name, $input) === 0) {
                return $row;
            }
        }

        return null;
    }

    private static function matchesMasterCode(string $input): bool
    {
        return self::findByCode($input) !== null;
    }

    private static function canonicalCode(string $input): ?string
    {
        return self::findByCode($input)?->code;
    }
}
