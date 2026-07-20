<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ActiveExistsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_soft_deleted_master_ids(): void
    {
        $category = Category::query()->create([
            'code' => 'DEL',
            'name' => 'Deleted Cat',
            'slug' => 'deleted-cat',
            'is_active' => true,
        ]);
        $id = $category->id;
        $category->delete();

        $validator = Validator::make(
            ['category_id' => $id],
            ['category_id' => ActiveExists::rule('categories')],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_accepts_active_master_ids(): void
    {
        $category = Category::query()->create([
            'code' => 'OK',
            'name' => 'Active Cat',
            'slug' => 'active-cat',
            'is_active' => true,
        ]);

        $validator = Validator::make(
            ['category_id' => $category->id],
            ['category_id' => ActiveExists::rule('categories')],
        );

        $this->assertFalse($validator->fails());
    }
}
