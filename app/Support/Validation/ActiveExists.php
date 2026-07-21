<?php

namespace App\Support\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Exists rule that ignores soft-deleted rows and (when applicable) inactive masters.
 */
class ActiveExists
{
    /**
     * Tables that use SoftDeletes but do not have an is_active column.
     *
     * @var list<string>
     */
    private const WITHOUT_IS_ACTIVE = [
        'inventory_items',
    ];

    public static function rule(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column)->whereNull('deleted_at');

        if (! in_array($table, self::WITHOUT_IS_ACTIVE, true)) {
            $rule->where('is_active', true);
        }

        return $rule;
    }
}
