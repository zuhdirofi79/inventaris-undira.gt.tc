<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, subcategories: list<array{code: string, name: string}>}>
     */
    private array $categories = [
        'ELK' => [
            'name' => 'Elektronik',
            'subcategories' => [
                ['code' => 'LP', 'name' => 'Laptop'],
                ['code' => 'MN', 'name' => 'Monitor'],
                ['code' => 'PR', 'name' => 'Proyektor'],
            ],
        ],
        'FUR' => [
            'name' => 'Furniture',
            'subcategories' => [
                ['code' => 'MJ', 'name' => 'Meja'],
                ['code' => 'KR', 'name' => 'Kursi'],
                ['code' => 'LM', 'name' => 'Lemari'],
            ],
        ],
        'ATK' => [
            'name' => 'Alat Tulis',
            'subcategories' => [
                ['code' => 'KR', 'name' => 'Kertas'],
                ['code' => 'PL', 'name' => 'Pulpen'],
                ['code' => 'ST', 'name' => 'Stapler'],
            ],
        ],
        'LAB' => [
            'name' => 'Laboratorium',
            'subcategories' => [
                ['code' => 'MK', 'name' => 'Mikroskop'],
                ['code' => 'GU', 'name' => 'Gelas Ukur'],
                ['code' => 'RG', 'name' => 'Reagent'],
            ],
        ],
        'JAR' => [
            'name' => 'Jaringan',
            'subcategories' => [
                ['code' => 'RT', 'name' => 'Router'],
                ['code' => 'SW', 'name' => 'Switch'],
                ['code' => 'AP', 'name' => 'Access Point'],
            ],
        ],
        'KEB' => [
            'name' => 'Kebersihan',
            'subcategories' => [
                ['code' => 'VC', 'name' => 'Vacuum Cleaner'],
                ['code' => 'PL', 'name' => 'Alat Pel'],
            ],
        ],
        'KAM' => [
            'name' => 'Keamanan',
            'subcategories' => [
                ['code' => 'CC', 'name' => 'CCTV'],
                ['code' => 'AP', 'name' => 'APAR'],
            ],
        ],
        'PKT' => [
            'name' => 'Peralatan Kantor',
            'subcategories' => [
                ['code' => 'PT', 'name' => 'Printer'],
                ['code' => 'AC', 'name' => 'AC'],
                ['code' => 'WB', 'name' => 'Whiteboard'],
            ],
        ],
        'AST' => [
            'name' => 'Aset Tetap',
            'subcategories' => [
                ['code' => 'MS', 'name' => 'Mesin'],
                ['code' => 'GN', 'name' => 'Generator'],
            ],
        ],
        'ASB' => [
            'name' => 'Aset Bergerak',
            'subcategories' => [
                ['code' => 'LD', 'name' => 'Laptop Dinas'],
                ['code' => 'TB', 'name' => 'Tablet'],
                ['code' => 'HP', 'name' => 'Handphone'],
            ],
        ],
        'LNY' => [
            'name' => 'Lainnya',
            'subcategories' => [
                ['code' => 'AC', 'name' => 'Aksesoris'],
                ['code' => 'SP', 'name' => 'Sparepart'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->categories as $code => $categoryData) {
            $category = Category::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $categoryData['name'],
                    'slug' => Str::slug($categoryData['name']),
                    'description' => 'Kategori '.$categoryData['name'].' untuk inventaris UNDIRA.',
                    'is_active' => true,
                ]
            );

            foreach ($categoryData['subcategories'] as $subCategoryData) {
                SubCategory::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'code' => $subCategoryData['code'],
                    ],
                    [
                        'name' => $subCategoryData['name'],
                        'slug' => Str::slug($subCategoryData['name']),
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
