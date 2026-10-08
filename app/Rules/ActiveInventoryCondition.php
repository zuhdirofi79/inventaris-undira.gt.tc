<?php

namespace App\Rules;

use App\Support\InventoryConditionCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveInventoryCondition implements ValidationRule
{
    public function __construct(private ?string $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('Kondisi tidak valid. Pilih kondisi yang aktif pada Master Data Kondisi Aset.');

            return;
        }

        $canonical = InventoryConditionCatalog::canonicalActiveCode((string) $value);
        if ($canonical !== null) {
            return;
        }

        if (InventoryConditionCatalog::isSelectable((string) $value, $this->current)) {
            return;
        }

        $fail('Kondisi tidak valid. Pilih kondisi yang aktif pada Master Data Kondisi Aset.');
    }
}
