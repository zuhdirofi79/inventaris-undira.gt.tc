<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared search / status filter / sort for master-data index pages.
 */
final class MasterListQuery
{
    /**
     * @param  list<string>  $searchColumns
     * @param  array<string, string>  $sortable  request key => column
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $searchColumns,
        array $sortable = ['name' => 'name'],
        string $defaultSort = 'name',
        string $defaultDir = 'asc',
        bool $hasActive = true,
    ): Builder {
        $q = trim((string) $request->input('q', ''));
        if ($q !== '' && $searchColumns !== []) {
            $query->where(function (Builder $builder) use ($q, $searchColumns) {
                foreach ($searchColumns as $column) {
                    $builder->orWhere($column, 'like', "%{$q}%");
                }
            });
        }

        if ($hasActive && $request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = (string) $request->input('sort', $defaultSort);
        if (! array_key_exists($sort, $sortable)) {
            $sort = $defaultSort;
        }

        $dir = strtolower((string) $request->input('dir', $defaultDir)) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortable[$sort], $dir);
    }
}
