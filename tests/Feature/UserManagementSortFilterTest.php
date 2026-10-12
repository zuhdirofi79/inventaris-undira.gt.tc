<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class UserManagementSortFilterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_name_sort_newest_oldest_and_login_filters_combine_with_existing_filters(): void
    {
        $actor = $this->makeSuperAdmin([
            'name' => 'Actor Super',
            'email' => 'actor-sort@undira.ac.id',
        ]);
        $actor->forceFill(['last_login_at' => now()])->save();

        $ani = $this->directoryUser('Sort Ani', 'sort-ani@undira.ac.id', 'staff', now()->subDays(3), now()->subDays(10));
        $budi = $this->directoryUser('Sort Budi', 'sort-budi@undira.ac.id', 'staff', now()->subHour(), now()->subDays(91));
        $cici = $this->directoryUser('Sort Cici', 'sort-cici@undira.ac.id', 'teknisi', now()->subDays(2), now()->subDays(2));
        $never = $this->directoryUser('Sort Dina', 'sort-dina@undira.ac.id', 'staff', now()->subDays(20), null);
        $inactiveAccount = $this->directoryUser('Sort Eko', 'sort-eko@undira.ac.id', 'teknisi', now()->subDays(4), now()->subDays(120), false);
        $recentLogin = $this->directoryUser('Sort Fajar', 'sort-fajar@undira.ac.id', 'staff', now()->subDays(8), now()->subDays(30));

        $this->actingAs($actor);

        $asc = $this->namesFrom($this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'name_asc']))->assertOk()->getContent());
        $this->assertSame(['Sort Ani', 'Sort Budi', 'Sort Cici', 'Sort Dina', 'Sort Eko', 'Sort Fajar'], $asc);

        $desc = $this->namesFrom($this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'name_desc']))->assertOk()->getContent());
        $this->assertSame(['Sort Fajar', 'Sort Eko', 'Sort Dina', 'Sort Cici', 'Sort Budi', 'Sort Ani'], $desc);

        $newest = $this->namesFrom($this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'newest']))->assertOk()->getContent());
        $this->assertSame($budi->name, $newest[0]);
        $this->assertSame($never->name, $newest[array_key_last($newest)]);

        $oldest = $this->namesFrom($this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'oldest']))->assertOk()->getContent());
        $this->assertSame($never->name, $oldest[0]);
        $this->assertSame($budi->name, $oldest[array_key_last($oldest)]);

        $stale = $this->namesFrom($this->get(route('admin.users.index', [
            'q' => 'Sort',
            'status' => 'active',
            'sort' => 'inactive_90',
        ]))->assertOk()->getContent());
        $this->assertSame(['Sort Budi'], $stale);
        $this->assertNotContains($never->name, $stale);
        $this->assertNotContains($recentLogin->name, $stale);
        $this->assertNotContains($inactiveAccount->name, $stale);

        $neverHtml = $this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'never_login']))->assertOk()->getContent();
        $this->assertSame(['Sort Dina'], $this->namesFrom($neverHtml));
        $this->assertStringContainsString('Belum pernah', $neverHtml);
        $this->assertStringContainsString('Tidak login lebih dari 90 hari', $this->get(route('admin.users.index', [
            'q' => 'Sort Budi',
            'sort' => 'inactive_90',
        ]))->assertOk()->getContent());

        $combined = $this->namesFrom($this->get(route('admin.users.index', [
            'q' => 'sort-',
            'role' => 'teknisi',
            'status' => 'active',
            'sort' => 'name_asc',
        ]))->assertOk()->getContent());
        $this->assertSame([$cici->name], $combined);

        $kept = $this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'name_desc', 'status' => 'active']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="name_desc" selected', $kept);
        $this->assertStringContainsString('value="active" selected', $kept);
        $this->assertStringContainsString('href="'.route('admin.users.index').'"', $kept);

        $fallback = $this->namesFrom($this->get(route('admin.users.index', [
            'q' => 'Sort',
            'sort' => 'bukan-pilihan',
        ]))->assertOk()->getContent());
        $this->assertSame(['Sort Ani', 'Sort Budi', 'Sort Cici', 'Sort Dina', 'Sort Eko', 'Sort Fajar'], $fallback);

        $this->assertTrue($budi->fresh()->is_active);
        $this->assertNull($never->fresh()->last_login_at);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.users.index', ['q' => 'Sort', 'sort' => 'inactive_90']))->assertOk();
        $directorySelects = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'select * from "users"'))
            ->values();
        $this->assertCount(1, $directorySelects);
        $this->assertStringContainsString('last_login_at', $directorySelects->first());
        $this->assertStringContainsString('order by "last_login_at" asc, "id" asc', $directorySelects->first());
    }

    public function test_viewer_cannot_open_user_directory_filters(): void
    {
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)
            ->get(route('admin.users.index', ['sort' => 'never_login']))
            ->assertForbidden();
    }

    public function test_user_directory_still_renders_management_actions(): void
    {
        $actor = $this->makeSuperAdmin();

        $this->actingAs($actor)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Tambah User')
            ->assertSee('Filter Status')
            ->assertSee('Urutkan / Filter Pengguna')
            ->assertSee('Terapkan')
            ->assertSee('Reset');
    }

    private function directoryUser(
        string $name,
        string $email,
        string $role,
        \DateTimeInterface $createdAt,
        ?\DateTimeInterface $lastLogin,
        bool $active = true,
    ): User {
        $user = $this->makeUserWithRole($role, [
            'name' => $name,
            'email' => $email,
            'is_active' => $active,
        ]);
        $user->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'last_login_at' => $lastLogin,
        ])->save();

        return $user->fresh();
    }

    /**
     * @return list<string>
     */
    private function namesFrom(string $html): array
    {
        preg_match_all('/<div class="fw-semibold">(.*?)<\/div>/', $html, $matches);

        return array_values(array_filter(
            $matches[1],
            fn (string $name) => str_starts_with($name, 'Sort ')
        ));
    }
}
