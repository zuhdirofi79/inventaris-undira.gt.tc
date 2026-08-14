<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendAssetScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_work_order_index_does_not_load_global_chartjs_or_select2(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['admin']);

        $html = $this->actingAs($user)
            ->get(route('maintenances.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('chart.umd.min.js', $html);
        $this->assertStringNotContainsString('select2.min.js', $html);
        $this->assertStringNotContainsString('select2.min.css', $html);
        $this->assertStringContainsString('POLL_MS = 90000', $html);
        $this->assertStringContainsString('visibilitychange', $html);
    }

    public function test_dashboard_loads_chartjs_page_scoped(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['admin']);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('chart.umd.min.js', $html);
        $this->assertStringNotContainsString('select2.min.js', $html);
    }

    public function test_inventory_items_loads_select2_page_scoped(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['admin']);

        $html = $this->actingAs($user)
            ->get(route('inventory.items'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('select2.min.js', $html);
        $this->assertStringContainsString('select2.min.css', $html);
        $this->assertStringNotContainsString('chart.umd.min.js', $html);
    }
}
