<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_normalized_favicon_files_exist(): void
    {
        $this->assertFileExists(public_path('images/favicon-undira.png'));
        $this->assertFileExists(public_path('images/favicon-undira-32.png'));
        $this->assertFileExists(public_path('images/favicon-undira-180.png'));
        $this->assertFileExists(public_path('images/favicon-undira-192.png'));
        $this->assertFileExists(public_path('images/favicon-undira-512.png'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('images/LOGO UNDIRA (1) (1).png'));
    }

    public function test_login_and_dashboard_use_undira_favicon(): void
    {
        $loginHtml = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('favicon-undira', $loginHtml);
        $this->assertStringNotContainsString("asset('images/favicon.png')", $loginHtml);
        $this->assertDoesNotMatchRegularExpression(
            '/rel=["\']icon["\'][^>]+href=["\'][^"\']*images\/favicon\.png/',
            $loginHtml
        );

        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        $dashHtml = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('favicon-undira', $dashHtml);
        $this->assertDoesNotMatchRegularExpression(
            '/rel=["\']icon["\'][^>]+href=["\'][^"\']*images\/favicon\.png/',
            $dashHtml
        );
    }

    public function test_favicon_assets_are_public_image_files(): void
    {
        $png = public_path('images/favicon-undira.png');
        $ico = public_path('favicon.ico');

        $this->assertFileExists($png);
        $this->assertFileExists($ico);
        $this->assertGreaterThan(100, filesize($png));
        $this->assertGreaterThan(100, filesize($ico));

        $info = getimagesize($png);
        $this->assertNotFalse($info);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
        $this->assertSame($info[0], $info[1], 'Primary favicon should be square');
    }
}
