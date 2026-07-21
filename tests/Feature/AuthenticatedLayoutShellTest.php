<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedLayoutShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_layout_exposes_responsive_shell_hooks(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="sidebarToggle"', $html);
        $this->assertStringContainsString('aria-controls="main-sidebar"', $html);
        $this->assertStringContainsString('app-identity-title', $html);
        $this->assertStringContainsString('Inventaris UNDIRA', $html);
        $this->assertStringNotContainsString('app-identity-subtitle', $html);
        $this->assertStringContainsString('Universitas Dian Nusantara', $html); // footer / sidebar alt
        $this->assertStringContainsString('LOGO UNDIRA.png', $html);
        $this->assertStringContainsString('undira-brand-mark', $html);
        $this->assertStringContainsString('id="sidebarBackdrop"', $html);
        $this->assertStringContainsString('undira-layout.css', $html);
        $this->assertStringContainsString('Apply layout mode + saved theme before first paint', $html);
        $this->assertStringContainsString('max-width: 1199.98px', $html);
        $this->assertStringContainsString('max-width: 767.98px', $html);
        $this->assertStringContainsString('breadcrumb-undira', $html);
        // Identity must not be hidden on small screens via Bootstrap d-none
        $this->assertStringNotContainsString('d-none d-sm-inline-block', $html);
    }

    public function test_layout_css_declares_unified_shell_variables(): void
    {
        $css = file_get_contents(public_path('css/undira-layout.css'));

        $this->assertStringContainsString('--sidebar-expanded-width: 248px', $css);
        $this->assertStringContainsString('--sidebar-collapsed-width: 76px', $css);
        $this->assertStringContainsString('--topbar-height: 64px', $css);
        $this->assertStringContainsString('--content-padding-x', $css);
        $this->assertStringContainsString('@media (min-width: 768px) and (max-width: 1199.98px)', $css);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $css);
        $this->assertStringContainsString('object-fit: contain', $css);
    }
}
