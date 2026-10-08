<?php

namespace App\Http\Requests;

use App\Enums\InventoryStatus;
use App\Rules\ActiveInventoryCondition;
use App\Support\InventoryConditionCatalog;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', ActiveExists::rule('categories')],
            'sub_category_id' => ['required', ActiveExists::rule('sub_categories')],
            'room_id' => ['required', ActiveExists::rule('rooms')],
            'brand' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'specification' => ['nullable', 'string'],
            'purchase_year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'purchase_date' => ['nullable', 'date'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'procurement_type_id' => ['required', ActiveExists::rule('procurement_types')],
            'price' => ['nullable', 'numeric', 'min:0'],
            'depreciation_value' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['required', new ActiveInventoryCondition()],
            'status' => ['required', Rule::enum(InventoryStatus::class)],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'pic_id' => ['nullable', 'exists:users,id'],
            'org_unit_id' => ['nullable', ActiveExists::rule('org_units')],
            'supplier_id' => ['nullable', ActiveExists::rule('suppliers')],
            'vendor_id' => ['nullable', ActiveExists::rule('vendors')],
            'warranty_info' => ['nullable', 'string'],
            'warranty_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang wajib diisi.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'sub_category_id.required' => 'Subkategori wajib dipilih.',
            'room_id.required' => 'Ruangan wajib dipilih.',
            'purchase_year.required' => 'Tahun pengadaan wajib diisi.',
            'procurement_type_id.required' => 'Jenis pengadaan wajib dipilih.',
            'purchase_date.date' => 'Tanggal pembelian tidak valid.',
            'price.min' => 'Harga tidak boleh negatif.',
            'condition.required' => 'Kondisi barang wajib dipilih.',
            'status.required' => 'Status barang wajib dipilih.',
            'pic_id.exists' => 'PIC yang dipilih tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Inventory number is server-generated and immutable.
        $this->request->remove('inventory_number');

        $payload = [
            'price' => $this->filled('price') ? $this->input('price') : null,
            'depreciation_value' => $this->filled('depreciation_value') ? $this->input('depreciation_value') : null,
            'pic_id' => $this->filled('pic_id') ? $this->input('pic_id') : null,
        ];

        if ($this->filled('condition')) {
            $canonical = InventoryConditionCatalog::canonicalActiveCode((string) $this->input('condition'));
            if ($canonical !== null) {
                $payload['condition'] = $canonical;
            }
        }

        $this->merge($payload);
    }
}
