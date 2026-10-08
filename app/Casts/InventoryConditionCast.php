<?php

namespace App\Casts;

use App\Enums\InventoryCondition;
use App\Support\InventoryConditionValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class InventoryConditionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?InventoryConditionValue
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new InventoryConditionValue((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof InventoryCondition) {
            return $value->value;
        }

        if ($value instanceof InventoryConditionValue) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof InventoryConditionValue) {
            return $value->value;
        }

        return $value === null ? null : (string) $value;
    }

    public function compare(Model $model, string $key, mixed $original, mixed $value): bool
    {
        $left = $original instanceof InventoryConditionValue ? $original->value : $original;
        $right = $value instanceof InventoryConditionValue ? $value->value : $value;

        return (string) $left === (string) $right;
    }
}
