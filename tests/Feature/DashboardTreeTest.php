<?php

namespace Tests\Feature;

use App\Models\Campus;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DashboardTreeTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_dashboard_tree_returns_campus_nodes_for_authenticated_user(): void
    {
        $user = $this->makeViewer();

        Campus::query()->create([
            'code' => 'CBB',
            'name' => 'Cibubur',
            'slug' => 'cibubur',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.tree'));

        $response->assertOk()
            ->assertJsonStructure(['nodes' => [['type', 'id', 'label', 'code', 'has_children', 'stats', 'children_url']]]);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
