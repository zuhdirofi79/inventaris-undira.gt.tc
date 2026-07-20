<?php

namespace App\Imports;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Vendor;
use App\Services\InventoryCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InventoryImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    public array $preview = [];

    public int $imported = 0;

    public function __construct(
        protected bool $previewOnly = false,
        protected ?int $userId = null,
        protected ?InventoryCodeService $codeService = null,
    ) {
        $this->codeService = $codeService ?? app(InventoryCodeService::class);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $row->toArray();

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'category_code' => ['required', 'string'],
                'room_code' => ['required', 'string'],
                'condition' => ['required', Rule::enum(InventoryCondition::class)],
                'status' => ['required', Rule::enum(InventoryStatus::class)],
                'purchase_year' => ['nullable', 'integer'],
                'purchase_date' => ['nullable', 'date'],
                'price' => ['nullable', 'numeric', 'min:0'],
                'quantity' => ['nullable', 'integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            $category = Category::query()->where('code', $data['category_code'])->first();
            $room = Room::query()->where('code', $data['room_code'])->first();

            if (! $category || ! $room) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['Kategori atau ruangan tidak ditemukan.'],
                ];

                continue;
            }

            $subCategory = null;
            if (! empty($data['sub_category_code'])) {
                $subCategory = SubCategory::query()
                    ->where('category_id', $category->id)
                    ->where('code', $data['sub_category_code'])
                    ->first();
            }

            $supplier = ! empty($data['supplier_name'])
                ? Supplier::query()->firstOrCreate(['name' => $data['supplier_name']])
                : null;

            $vendor = ! empty($data['vendor_name'])
                ? Vendor::query()->firstOrCreate(['name' => $data['vendor_name']])
                : null;

            $previewRow = [
                'row' => $rowNumber,
                'name' => $data['name'],
                'category' => $category->name,
                'room' => $room->name,
                'status' => $data['status'],
                'condition' => $data['condition'],
            ];

            $this->preview[] = $previewRow;

            if ($this->previewOnly) {
                continue;
            }

            DB::transaction(function () use ($data, $category, $subCategory, $room, $supplier, $vendor) {
                $groupKey = $this->codeService->generateGroupKey($data['name'], $room->id, $category->id);
                $quantity = (int) ($data['quantity'] ?? 1);

                for ($i = 0; $i < $quantity; $i++) {
                    $inventoryNumber = $this->codeService->generateInventoryNumber(
                        $room,
                        $category,
                        $subCategory,
                    );
                    $unitSequence = $i + 1;

                    $item = InventoryItem::query()->create([
                        'inventory_number' => $inventoryNumber,
                        'name' => $data['name'],
                        'category_id' => $category->id,
                        'sub_category_id' => $subCategory?->id,
                        'room_id' => $room->id,
                        'brand' => $data['brand'] ?? null,
                        'type' => $data['type'] ?? null,
                        'model' => $data['model'] ?? null,
                        'serial_number' => $data['serial_number'] ?? null,
                        'specification' => $data['specification'] ?? null,
                        'purchase_year' => $data['purchase_year'] ?? null,
                        'purchase_date' => $data['purchase_date'] ?? null,
                        'funding_source' => $data['funding_source'] ?? null,
                        'price' => $data['price'] ?? null,
                        'condition' => $data['condition'],
                        'status' => $data['status'],
                        'responsible_person' => $data['responsible_person'] ?? null,
                        'supplier_id' => $supplier?->id,
                        'vendor_id' => $vendor?->id,
                        'warranty_info' => $data['warranty_info'] ?? null,
                        'warranty_expiry' => $data['warranty_expiry'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'quantity' => 1,
                        'unit' => $data['unit'] ?? 'Unit',
                        'group_key' => $groupKey,
                        'unit_sequence' => $unitSequence,
                        'created_by' => $this->userId,
                        'updated_by' => $this->userId,
                    ]);

                    $this->codeService->generateAndSaveCodes($item);
                    $this->imported++;
                }
            });
        }
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
