<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InventorySeeder extends Seeder
{
    /** @var array<string, int> */
    private array $sequenceCounters = [];

    public function run(): void
    {
        $createdBy = User::query()->where('email', 'azwar.syifa.mufiqi@undira.ac.id')->value('id');
        $supplier = Supplier::query()->first();
        $vendor = Vendor::query()->first();

        $subCategories = SubCategory::query()
            ->with('category')
            ->get()
            ->keyBy(fn (SubCategory $subCategory) => $subCategory->category->code.'-'.$subCategory->code);

        $this->seedClassroomGroups($subCategories, $createdBy, $supplier?->id, $vendor?->id);
        $this->seedLaboratoryItems($subCategories, $createdBy, $supplier?->id, $vendor?->id);
        $this->seedServerRoomItems($subCategories, $createdBy, $supplier?->id, $vendor?->id);
        $this->seedAdminOfficeItems($subCategories, $createdBy, $supplier?->id, $vendor?->id);
        $this->seedLibraryItems($subCategories, $createdBy, $supplier?->id, $vendor?->id);
    }

    /**
     * @param  Collection<string, SubCategory>  $subCategories
     */
    private function seedClassroomGroups(
        Collection $subCategories,
        ?int $createdBy,
        ?int $supplierId,
        ?int $vendorId
    ): void {
        $classrooms = Room::query()
            ->where('room_type', 'Kelas')
            ->whereHas('floor.building.campus', fn ($query) => $query->whereIn('code', ['CBB', 'TJD', 'GVL']))
            ->with('floor.building.campus')
            ->limit(9)
            ->get();

        foreach ($classrooms as $index => $room) {
            $mejaSubCategory = $subCategories->get('FUR-MJ');
            $kursiSubCategory = $subCategories->get('FUR-KR');

            if (! $mejaSubCategory || ! $kursiSubCategory) {
                continue;
            }

            $groupKeyMeja = $this->buildGroupKey($room, 'MEJA');
            $groupKeyKursi = $this->buildGroupKey($room, 'KURSI');

            $this->createGroupedItem(
                room: $room,
                subCategory: $mejaSubCategory,
                name: 'Meja Kelas Standard',
                quantity: 20,
                unit: 'Unit',
                groupKey: $groupKeyMeja,
                brand: 'Chitose',
                type: 'Meja Belajar',
                price: 850000,
                condition: 'bagus',
                status: 'aktif',
                purchaseYear: 2024,
                createdBy: $createdBy,
                supplierId: $supplierId,
                vendorId: $vendorId,
            );

            $this->createGroupedItem(
                room: $room,
                subCategory: $kursiSubCategory,
                name: 'Kursi Kelas Standard',
                quantity: 40,
                unit: 'Unit',
                groupKey: $groupKeyKursi,
                brand: 'Chitose',
                type: 'Kursi Lipat',
                price: 350000,
                condition: 'bagus',
                status: 'aktif',
                purchaseYear: 2024,
                createdBy: $createdBy,
                supplierId: $supplierId,
                vendorId: $vendorId,
            );

            if ($index % 3 === 0) {
                $proyektorSubCategory = $subCategories->get('ELK-PR');

                if ($proyektorSubCategory) {
                    $this->createIndividualItem(
                        room: $room,
                        subCategory: $proyektorSubCategory,
                        name: 'Proyektor Ruang Kelas',
                        brand: 'Epson',
                        model: 'EB-X06',
                        serialNumber: 'EPS-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        price: 6500000,
                        condition: 'bagus',
                        status: 'aktif',
                        purchaseYear: 2023,
                        createdBy: $createdBy,
                        supplierId: $supplierId,
                        vendorId: $vendorId,
                    );
                }
            }
        }
    }

    /**
     * @param  Collection<string, SubCategory>  $subCategories
     */
    private function seedLaboratoryItems(
        Collection $subCategories,
        ?int $createdBy,
        ?int $supplierId,
        ?int $vendorId
    ): void {
        $labs = Room::query()
            ->where('room_type', 'Laboratorium')
            ->with('floor.building.campus')
            ->limit(6)
            ->get();

        $labItems = [
            ['sub' => 'LAB-MK', 'name' => 'Mikroskop Binokuler', 'brand' => 'Olympus', 'model' => 'CX23', 'price' => 12500000],
            ['sub' => 'LAB-GU', 'name' => 'Gelas Ukur 1000ml', 'brand' => 'Pyrex', 'model' => 'GR-1000', 'price' => 185000, 'quantity' => 24, 'unit' => 'Buah'],
            ['sub' => 'ELK-LP', 'name' => 'Laptop Laboratorium', 'brand' => 'Lenovo', 'model' => 'ThinkPad E14', 'price' => 9800000],
            ['sub' => 'ELK-MN', 'name' => 'Monitor Laboratorium', 'brand' => 'LG', 'model' => '24MP400', 'price' => 2100000],
        ];

        foreach ($labs as $labIndex => $room) {
            foreach ($labItems as $itemIndex => $item) {
                $subCategory = $subCategories->get($item['sub']);

                if (! $subCategory) {
                    continue;
                }

                if (isset($item['quantity']) && $item['quantity'] > 1) {
                    $this->createGroupedItem(
                        room: $room,
                        subCategory: $subCategory,
                        name: $item['name'],
                        quantity: $item['quantity'],
                        unit: $item['unit'] ?? 'Unit',
                        groupKey: $this->buildGroupKey($room, strtoupper($item['sub'])),
                        brand: $item['brand'],
                        type: $item['name'],
                        model: $item['model'] ?? null,
                        price: $item['price'],
                        condition: 'bagus',
                        status: 'aktif',
                        purchaseYear: 2024,
                        createdBy: $createdBy,
                        supplierId: $supplierId,
                        vendorId: $vendorId,
                    );

                    continue;
                }

                $this->createIndividualItem(
                    room: $room,
                    subCategory: $subCategory,
                    name: $item['name'],
                    brand: $item['brand'],
                    model: $item['model'] ?? null,
                    serialNumber: 'LAB-'.($labIndex + 1).'-'.($itemIndex + 1),
                    price: $item['price'],
                    condition: 'bagus',
                    status: 'aktif',
                    purchaseYear: 2024,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                );
            }
        }
    }

    /**
     * @param  Collection<string, SubCategory>  $subCategories
     */
    private function seedServerRoomItems(
        Collection $subCategories,
        ?int $createdBy,
        ?int $supplierId,
        ?int $vendorId
    ): void {
        $serverRooms = Room::query()
            ->where('room_type', 'Server Room')
            ->with('floor.building.campus')
            ->limit(3)
            ->get();

        $networkItems = [
            ['sub' => 'JAR-RT', 'name' => 'Router Utama', 'brand' => 'Cisco', 'model' => 'ISR 4331'],
            ['sub' => 'JAR-SW', 'name' => 'Switch Core', 'brand' => 'Cisco', 'model' => 'C9200-24P'],
            ['sub' => 'JAR-AP', 'name' => 'Access Point', 'brand' => 'Ubiquiti', 'model' => 'U6-Pro'],
            ['sub' => 'KAM-CC', 'name' => 'CCTV Server Room', 'brand' => 'Hikvision', 'model' => 'DS-7608NI'],
        ];

        foreach ($serverRooms as $roomIndex => $room) {
            foreach ($networkItems as $itemIndex => $item) {
                $subCategory = $subCategories->get($item['sub']);

                if (! $subCategory) {
                    continue;
                }

                $this->createIndividualItem(
                    room: $room,
                    subCategory: $subCategory,
                    name: $item['name'],
                    brand: $item['brand'],
                    model: $item['model'],
                    serialNumber: 'NET-'.($roomIndex + 1).'-'.($itemIndex + 1),
                    price: 15000000,
                    condition: 'bagus',
                    status: 'aktif',
                    purchaseYear: 2025,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                    fundingSource: 'APBN',
                );
            }
        }
    }

    /**
     * @param  Collection<string, SubCategory>  $subCategories
     */
    private function seedAdminOfficeItems(
        Collection $subCategories,
        ?int $createdBy,
        ?int $supplierId,
        ?int $vendorId
    ): void {
        $adminRooms = Room::query()
            ->where('room_type', 'Ruang Administrasi')
            ->with('floor.building.campus')
            ->limit(3)
            ->get();

        foreach ($adminRooms as $roomIndex => $room) {
            $printerSubCategory = $subCategories->get('PKT-PT');
            $acSubCategory = $subCategories->get('PKT-AC');

            if ($printerSubCategory) {
                $this->createIndividualItem(
                    room: $room,
                    subCategory: $printerSubCategory,
                    name: 'Printer Multifungsi',
                    brand: 'HP',
                    model: 'LaserJet Pro M428',
                    serialNumber: 'HP-ADM-'.($roomIndex + 1),
                    price: 7200000,
                    condition: 'bagus',
                    status: 'aktif',
                    purchaseYear: 2024,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                );
            }

            if ($acSubCategory) {
                $this->createIndividualItem(
                    room: $room,
                    subCategory: $acSubCategory,
                    name: 'AC Split Ruang Administrasi',
                    brand: 'Daikin',
                    model: 'FTKC25NV16',
                    serialNumber: 'AC-ADM-'.($roomIndex + 1),
                    price: 8900000,
                    condition: 'cukup',
                    status: 'aktif',
                    purchaseYear: 2022,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                );
            }
        }
    }

    /**
     * @param  Collection<string, SubCategory>  $subCategories
     */
    private function seedLibraryItems(
        Collection $subCategories,
        ?int $createdBy,
        ?int $supplierId,
        ?int $vendorId
    ): void {
        $libraries = Room::query()
            ->where('room_type', 'Perpustakaan')
            ->with('floor.building.campus')
            ->limit(3)
            ->get();

        foreach ($libraries as $room) {
            $mejaSubCategory = $subCategories->get('FUR-MJ');
            $kursiSubCategory = $subCategories->get('FUR-KR');

            if ($mejaSubCategory) {
                $this->createGroupedItem(
                    room: $room,
                    subCategory: $mejaSubCategory,
                    name: 'Meja Baca Perpustakaan',
                    quantity: 12,
                    unit: 'Unit',
                    groupKey: $this->buildGroupKey($room, 'MEJA-BACA'),
                    brand: 'Olympic',
                    type: 'Meja Baca',
                    price: 1200000,
                    condition: 'bagus',
                    status: 'aktif',
                    purchaseYear: 2023,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                );
            }

            if ($kursiSubCategory) {
                $this->createGroupedItem(
                    room: $room,
                    subCategory: $kursiSubCategory,
                    name: 'Kursi Baca Perpustakaan',
                    quantity: 24,
                    unit: 'Unit',
                    groupKey: $this->buildGroupKey($room, 'KURSI-BACA'),
                    brand: 'Olympic',
                    type: 'Kursi Baca',
                    price: 450000,
                    condition: 'bagus',
                    status: 'aktif',
                    purchaseYear: 2023,
                    createdBy: $createdBy,
                    supplierId: $supplierId,
                    vendorId: $vendorId,
                );
            }
        }
    }

    private function createGroupedItem(
        Room $room,
        SubCategory $subCategory,
        string $name,
        int $quantity,
        string $unit,
        string $groupKey,
        ?string $brand = null,
        ?string $type = null,
        ?string $model = null,
        ?float $price = null,
        string $condition = 'bagus',
        string $status = 'aktif',
        ?int $purchaseYear = null,
        ?int $createdBy = null,
        ?int $supplierId = null,
        ?int $vendorId = null,
        ?string $fundingSource = null,
    ): void {
        $inventoryNumber = $this->nextInventoryNumber($room, $subCategory);

        InventoryItem::updateOrCreate(
            ['inventory_number' => $inventoryNumber],
            [
                'name' => $name,
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
                'brand' => $brand,
                'type' => $type,
                'model' => $model,
                'purchase_year' => $purchaseYear,
                'purchase_date' => $purchaseYear ? $purchaseYear.'-01-15' : null,
                'funding_source' => $fundingSource ?? 'BOS',
                'price' => $price,
                'condition' => $condition,
                'status' => $status,
                'room_id' => $room->id,
                'responsible_person' => 'Staff Sarana UNDIRA',
                'supplier_id' => $supplierId,
                'vendor_id' => $vendorId,
                'quantity' => $quantity,
                'unit' => $unit,
                'group_key' => $groupKey,
                'unit_sequence' => null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]
        );
    }

    private function createIndividualItem(
        Room $room,
        SubCategory $subCategory,
        string $name,
        ?string $brand = null,
        ?string $model = null,
        ?string $serialNumber = null,
        ?float $price = null,
        string $condition = 'bagus',
        string $status = 'aktif',
        ?int $purchaseYear = null,
        ?int $createdBy = null,
        ?int $supplierId = null,
        ?int $vendorId = null,
        ?string $fundingSource = null,
    ): void {
        $inventoryNumber = $this->nextInventoryNumber($room, $subCategory);

        InventoryItem::updateOrCreate(
            ['inventory_number' => $inventoryNumber],
            [
                'name' => $name,
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
                'brand' => $brand,
                'model' => $model,
                'serial_number' => $serialNumber,
                'purchase_year' => $purchaseYear,
                'purchase_date' => $purchaseYear ? $purchaseYear.'-06-01' : null,
                'funding_source' => $fundingSource ?? 'BOS',
                'price' => $price,
                'condition' => $condition,
                'status' => $status,
                'room_id' => $room->id,
                'responsible_person' => 'Staff IT UNDIRA',
                'supplier_id' => $supplierId,
                'vendor_id' => $vendorId,
                'quantity' => 1,
                'unit' => 'Unit',
                'group_key' => null,
                'unit_sequence' => 1,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]
        );
    }

    private function nextInventoryNumber(Room $room, SubCategory $subCategory): string
    {
        $room->loadMissing('floor.building.campus');

        $campusCode = $room->floor->building->campus->code;
        $buildingCode = $room->floor->building->code;
        $floorCode = $room->floor->code;
        $roomCode = $room->code;
        $subCategoryCode = $subCategory->code;

        $counterKey = implode('-', [$campusCode, $buildingCode, $floorCode, $roomCode, $subCategoryCode]);
        $this->sequenceCounters[$counterKey] = ($this->sequenceCounters[$counterKey] ?? 0) + 1;
        $sequence = str_pad((string) $this->sequenceCounters[$counterKey], 4, '0', STR_PAD_LEFT);

        return "UND-{$campusCode}-{$buildingCode}-{$floorCode}-{$roomCode}-{$subCategoryCode}-{$sequence}";
    }

    private function buildGroupKey(Room $room, string $suffix): string
    {
        $room->loadMissing('floor.building.campus');

        return implode('-', [
            'UND',
            $room->floor->building->campus->code,
            $room->floor->building->code,
            $room->floor->code,
            $room->code,
            $suffix,
        ]);
    }
}
