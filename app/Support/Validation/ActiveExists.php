<?php

namespace App\Support\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Exists rule that ignores soft-deleted rows.
 */
class ActiveExists
{
    public static function rule(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->whereNull('deleted_at');
    }
}
