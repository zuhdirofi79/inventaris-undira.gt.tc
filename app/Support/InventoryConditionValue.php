<?php

namespace App\Support;

/**
 * Stored inventory condition. Value is the raw column (master code or legacy enum value).
 */
final class InventoryConditionValue
{
    public function __construct(public string $value) {}

    public function label(): string
    {
        return InventoryConditionCatalog::label($this->value);
    }

    public function badgeTone(): string
    {
        return InventoryConditionCatalog::tone($this->value);
    }
}
