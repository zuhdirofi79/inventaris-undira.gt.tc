<?php

namespace App\Http\Requests;

use App\Models\Building;
use App\Models\Floor;
use App\Models\InventoryMasterConfiguration;
use App\Models\Room;
use App\Models\SubCategory;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInventoryMasterConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('category.manage') || $user?->can('inventory.create')) ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('configuration') !== null;
        $creatingAssets = ! $isUpdate && $this->boolean('create_assets', true);

        return [
            'campus_id' => ['required', ActiveExists::rule('campuses')],
            'building_id' => ['required', ActiveExists::rule('buildings')],
            'floor_id' => ['required', ActiveExists::rule('floors')],
            'org_unit_id' => ['required', ActiveExists::rule('org_units')],
            'room_function_id' => ['nullable', ActiveExists::rule('room_functions')],
            'room_id' => ['required', ActiveExists::rule('rooms')],
            'procurement_type_id' => ['required', ActiveExists::rule('procurement_types')],
            'purchase_year_id' => ['required', ActiveExists::rule('purchase_years')],
            'category_id' => ['required', ActiveExists::rule('categories')],
            'sub_category_id' => ['required', ActiveExists::rule('sub_categories')],
            'inventory_condition_id' => ['required', ActiveExists::rule('inventory_conditions')],
            'is_active' => ['nullable', 'boolean'],
            'create_assets' => ['nullable', 'boolean'],
            // Physical inventory (required when creating assets from Konfigurasi)
            'asset_name' => [$creatingAssets ? 'required' : 'nullable', 'string', 'max:255'],
            'quantity' => [$creatingAssets ? 'required' : 'nullable', 'integer', 'min:1', 'max:1000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_name.required' => 'Nama barang inventaris wajib diisi.',
            'quantity.required' => 'Jumlah unit wajib diisi.',
            'quantity.min' => 'Jumlah minimal 1 unit.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $campusId = (int) $this->input('campus_id');
            $buildingId = (int) $this->input('building_id');
            $floorId = (int) $this->input('floor_id');
            $roomId = (int) $this->input('room_id');
            $categoryId = (int) $this->input('category_id');
            $subCategoryId = (int) $this->input('sub_category_id');
            $isUpdate = $this->route('configuration') !== null;
            $creatingAssets = ! $isUpdate && $this->boolean('create_assets', true) && filled($this->input('asset_name'));

            $building = Building::query()->find($buildingId);
            if (! $building || (int) $building->campus_id !== $campusId) {
                $validator->errors()->add('building_id', 'Gedung tidak termasuk kampus yang dipilih.');
            }

            $floor = Floor::query()->find($floorId);
            if (! $floor || (int) $floor->building_id !== $buildingId) {
                $validator->errors()->add('floor_id', 'Lantai tidak termasuk gedung yang dipilih.');
            }

            $room = Room::query()->find($roomId);
            if (! $room || (int) $room->floor_id !== $floorId) {
                $validator->errors()->add('room_id', 'Ruangan tidak termasuk lantai yang dipilih.');
            }

            $sub = SubCategory::query()->find($subCategoryId);
            if (! $sub || (int) $sub->category_id !== $categoryId) {
                $validator->errors()->add('sub_category_id', 'Subklasifikasi tidak termasuk klasifikasi yang dipilih.');
            }

            foreach ([
                'org_units' => ['org_unit_id', 'Unit / Divisi'],
                'procurement_types' => ['procurement_type_id', 'Jenis Pengadaan'],
                'purchase_years' => ['purchase_year_id', 'Tahun Pembelian'],
                'categories' => ['category_id', 'Klasifikasi'],
                'sub_categories' => ['sub_category_id', 'Subklasifikasi'],
                'inventory_conditions' => ['inventory_condition_id', 'Kondisi'],
                'campuses' => ['campus_id', 'Kampus'],
                'buildings' => ['building_id', 'Gedung'],
                'floors' => ['floor_id', 'Lantai'],
                'rooms' => ['room_id', 'Ruangan'],
            ] as $table => [$field, $label]) {
                $id = $this->input($field);
                if (! $id) {
                    continue;
                }
                $active = \Illuminate\Support\Facades\DB::table($table)
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->exists();
                if (! $active) {
                    $validator->errors()->add($field, "{$label} tidak aktif atau tidak tersedia.");
                }
            }

            if ($this->filled('room_function_id')) {
                $rfActive = \Illuminate\Support\Facades\DB::table('room_functions')
                    ->where('id', $this->input('room_function_id'))
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->exists();
                if (! $rfActive) {
                    $validator->errors()->add('room_function_id', 'Fungsi ruang tidak aktif atau tidak tersedia.');
                }
            }

            // Duplikat konfigurasi hanya ditolak jika TIDAK membuat aset fisik
            if ($creatingAssets) {
                return;
            }

            $ignoreId = $this->route('configuration')?->id;
            $duplicate = InventoryMasterConfiguration::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('campus_id', $campusId)
                ->where('building_id', $buildingId)
                ->where('floor_id', $floorId)
                ->where('org_unit_id', $this->input('org_unit_id'))
                ->where('room_id', $roomId)
                ->where('procurement_type_id', $this->input('procurement_type_id'))
                ->where('purchase_year_id', $this->input('purchase_year_id'))
                ->where('category_id', $categoryId)
                ->where('sub_category_id', $subCategoryId)
                ->where('inventory_condition_id', $this->input('inventory_condition_id'))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('campus_id', 'Konfigurasi dengan kombinasi master yang sama sudah ada.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'create_assets' => $this->boolean('create_assets', true),
            'price' => $this->filled('price') ? $this->input('price') : null,
        ]);
    }
}
