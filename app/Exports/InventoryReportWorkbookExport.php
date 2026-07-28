<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Workbook: Ringkasan + Komposisi Kategori + Komposisi Sub Kategori + Detail.
 */
class InventoryReportWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $summary
     * @param  array<string, string|null>  $filters
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        protected array $tree,
        protected array $summary,
        protected array $filters,
        protected array $meta,
    ) {}

    public function sheets(): array
    {
        $composition = $this->summary['composition'] ?? [];

        return [
            new InventoryCompositionRingkasanExport($this->summary, $this->filters),
            new InventoryCompositionCategoryExport($composition['by_category'] ?? []),
            new InventorySubCategoryRecapExport($this->summary['sub_category_recap'] ?? ($composition['by_sub_category'] ?? [])),
            new InventoryHierarchicalExport($this->tree, $this->summary, $this->filters, $this->meta),
        ];
    }
}
