<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterArrayExport implements FromCollection, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  Collection<int, array<string, mixed>>|list<array<string, mixed>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected Collection|array $rows,
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows instanceof Collection
            ? $this->rows
            : collect($this->rows);
    }
}
