<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CampusSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, description: string}>
     */
    private array $campuses = [
        [
            'code' => 'CBB',
            'name' => 'Undira Cibubur',
            'description' => 'Kampus Utama UNDIRA di Cibubur, Jakarta Timur.',
        ],
        [
            'code' => 'TJD',
            'name' => 'Undira Tanjung Duren',
            'description' => 'Kampus UNDIRA di Tanjung Duren, Jakarta Barat.',
        ],
        [
            'code' => 'GVL',
            'name' => 'Undira Greenville',
            'description' => 'Kampus UNDIRA di Greenville, Jakarta Barat.',
        ],
    ];

    /**
     * @var list<string>
     */
    private array $buildingCodes = ['A', 'B', 'C', 'D'];

    /**
     * @var list<string>
     */
    private array $roomTypes = [
        'Kelas',
        'Laboratorium',
        'Ruang Dosen',
        'Ruang Administrasi',
        'Gudang',
        'Server Room',
        'Meeting Room',
        'Perpustakaan',
        'Toilet',
        'Lobby',
    ];

    public function run(): void
    {
        foreach ($this->campuses as $campusData) {
            $campus = Campus::updateOrCreate(
                ['code' => $campusData['code']],
                [
                    'name' => $campusData['name'],
                    'slug' => Str::slug($campusData['name']),
                    'description' => $campusData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($this->buildingCodes as $buildingIndex => $buildingCode) {
                $building = Building::updateOrCreate(
                    [
                        'campus_id' => $campus->id,
                        'code' => $buildingCode,
                    ],
                    [
                        'name' => 'Gedung '.$buildingCode,
                        'slug' => Str::slug('gedung-'.$buildingCode.'-'.$campus->code),
                        'description' => 'Gedung '.$buildingCode.' '.$campus->name,
                        'is_active' => true,
                    ]
                );

                for ($floorNumber = 1; $floorNumber <= 4; $floorNumber++) {
                    $floorCode = 'L'.$floorNumber;

                    $floor = Floor::updateOrCreate(
                        [
                            'building_id' => $building->id,
                            'code' => $floorCode,
                        ],
                        [
                            'name' => 'Lantai '.$floorNumber,
                            'floor_number' => $floorNumber,
                            'is_active' => true,
                        ]
                    );

                    $roomCount = 5 + (($buildingIndex + $floorNumber) % 4);
                    $roomTypesForFloor = $this->roomTypesForFloor($floorNumber, $roomCount);

                    foreach ($roomTypesForFloor as $roomIndex => $roomType) {
                        $roomSequence = $roomIndex + 1;
                        $roomCode = $this->generateRoomCode($buildingCode, $floorNumber, $roomSequence, $roomType);
                        $roomName = $this->generateRoomName($roomType, $roomCode);

                        Room::updateOrCreate(
                            [
                                'floor_id' => $floor->id,
                                'code' => $roomCode,
                            ],
                            [
                                'name' => $roomName,
                                'room_type' => $roomType,
                                'capacity' => $this->capacityForRoomType($roomType),
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function roomTypesForFloor(int $floorNumber, int $roomCount): array
    {
        $prioritizedTypes = match ($floorNumber) {
            1 => ['Lobby', 'Ruang Administrasi', 'Kelas', 'Kelas', 'Toilet', 'Meeting Room', 'Perpustakaan', 'Gudang'],
            2 => ['Kelas', 'Kelas', 'Laboratorium', 'Ruang Dosen', 'Meeting Room', 'Toilet', 'Kelas', 'Server Room'],
            3 => ['Kelas', 'Laboratorium', 'Laboratorium', 'Ruang Dosen', 'Kelas', 'Meeting Room', 'Toilet', 'Perpustakaan'],
            default => ['Kelas', 'Kelas', 'Laboratorium', 'Ruang Dosen', 'Gudang', 'Server Room', 'Meeting Room', 'Toilet'],
        };

        return array_slice($prioritizedTypes, 0, $roomCount);
    }

    private function generateRoomCode(string $buildingCode, int $floorNumber, int $roomSequence, string $roomType): string
    {
        $suffix = $floorNumber.str_pad((string) $roomSequence, 2, '0', STR_PAD_LEFT);

        return match ($roomType) {
            'Kelas' => $buildingCode.$suffix,
            'Laboratorium' => 'LAB-'.$buildingCode.$suffix,
            'Ruang Dosen' => 'DOS-'.$buildingCode.$suffix,
            'Ruang Administrasi' => 'ADM-'.$buildingCode.$suffix,
            'Gudang' => 'GDG-'.$buildingCode.$suffix,
            'Server Room' => 'SRV-'.$buildingCode.$suffix,
            'Meeting Room' => 'MTG-'.$buildingCode.$suffix,
            'Perpustakaan' => 'LIB-'.$buildingCode.$suffix,
            'Toilet' => 'WC-'.$buildingCode.$suffix,
            'Lobby' => 'LBY-'.$buildingCode.$suffix,
            default => $buildingCode.$suffix,
        };
    }

    private function generateRoomName(string $roomType, string $roomCode): string
    {
        return match ($roomType) {
            'Kelas' => 'Kelas '.$roomCode,
            'Laboratorium' => 'Laboratorium '.$roomCode,
            'Ruang Dosen' => 'Ruang Dosen '.$roomCode,
            'Ruang Administrasi' => 'Ruang Administrasi '.$roomCode,
            'Gudang' => 'Gudang '.$roomCode,
            'Server Room' => 'Server Room '.$roomCode,
            'Meeting Room' => 'Meeting Room '.$roomCode,
            'Perpustakaan' => 'Perpustakaan '.$roomCode,
            'Toilet' => 'Toilet '.$roomCode,
            'Lobby' => 'Lobby '.$roomCode,
            default => $roomType.' '.$roomCode,
        };
    }

    private function capacityForRoomType(string $roomType): ?int
    {
        return match ($roomType) {
            'Kelas' => 40,
            'Laboratorium' => 30,
            'Ruang Dosen' => 4,
            'Ruang Administrasi' => 15,
            'Gudang' => null,
            'Server Room' => 6,
            'Meeting Room' => 20,
            'Perpustakaan' => 50,
            'Toilet' => null,
            'Lobby' => 30,
            default => null,
        };
    }
}
