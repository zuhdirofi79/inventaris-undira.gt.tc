<?php

namespace Tests\Feature;

use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventorySelectorFormsTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_ops_create_forms_render_shared_inventory_selector(): void
    {
        $admin = $this->makeAdmin();

        foreach ([
            route('borrowings.create'),
            route('mutations.create'),
            route('maintenances.create'),
            route('maintenance-schedules.create'),
        ] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-inventory-selector', $html);
            $this->assertStringContainsString('inventory-selector.js', $html);
            $this->assertStringContainsString('Cari Berdasarkan Lokasi', $html);
        }
    }
}
