<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarkModeThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        return $user;
    }

    public function test_layout_exposes_theme_toggle_and_early_dark_mode_script(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="darkModeToggle"', false)
            ->assertSee('undira-dark-mode', false)
            ->getContent();

        $this->assertStringContainsString("localStorage.getItem('undira-dark-mode')", $html);
        $this->assertStringContainsString('classList.add(\'dark-mode\')', $html);
        $this->assertStringContainsString('--text-heading', file_get_contents(public_path('css/undira-modals.css')));
        $this->assertStringContainsString('body.dark-mode', file_get_contents(public_path('css/undira.css')));
    }

    public function test_floor_page_uses_semantic_room_card_title_classes(): void
    {
        $admin = $this->makeAdmin();
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Undira Cibubur', 'slug' => 'cibubur-dm', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'gedung-a-dm', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A103', 'name' => 'Kelas A103', 'room_type' => 'Kelas', 'is_active' => true,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('floors.show', $floor))
            ->assertOk()
            ->assertSee('Lantai 1')
            ->assertSee('Kelas A103')
            ->assertSee('A103')
            ->getContent();

        $this->assertStringContainsString('room-card-title', $html);
        $this->assertStringContainsString('room-card-code', $html);
        $this->assertStringContainsString('room-explore-card', $html);
        // Avoid light-mode-only navy utility on room names
        $this->assertStringNotContainsString('class="text-undira-blue mb-1 text-break"', $html);
    }

    public function test_dark_mode_css_does_not_use_global_white_text_override(): void
    {
        $css = file_get_contents(public_path('css/undira.css'))
            ."\n".file_get_contents(public_path('css/undira-modals.css'));

        $this->assertDoesNotMatchRegularExpression('/body\.dark-mode\s+\*\s*\{[^}]*color:\s*#?fff/i', $css);
        $this->assertDoesNotMatchRegularExpression('/body\.dark\s+\*\s*\{[^}]*color:\s*white/i', $css);
        $this->assertStringContainsString('--text-heading:', $css);
        $this->assertStringContainsString('--text-primary:', $css);
    }
}
