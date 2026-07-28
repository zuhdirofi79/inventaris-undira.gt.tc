<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Flat inventory Excel: Ringkasan + komposisi + detail rows.
 */
class InventoryFlatWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, string|null>  $filters
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public function __construct(
        protected array $summary,
        protected array $filters,
        protected array $headings,
        protected array $rows,
        protected string $sheetTitle = 'Detail Inventaris',
    ) {}

    public function sheets(): array
    {
        $composition = $this->summary['composition'] ?? [];

        return [
            new InventoryCompositionRingkasanExport($this->summary, $this->filters),
            new InventoryCompositionCategoryExport($composition['by_category'] ?? []),
            new InventorySubCategoryRecapExport($this->summary['sub_category_recap'] ?? ($composition['by_sub_category'] ?? [])),
            new EamFlatReportExport($this->headings, $this->rows, $this->sheetTitle),
        ];
    }
}
