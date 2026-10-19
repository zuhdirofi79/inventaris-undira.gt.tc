<?php

namespace Tests\Feature;

use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class SidebarLayoutTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_admin_sidebar_keeps_full_labels_and_places_arrows_beside_them(): void
    {
        $admin = $this->makeAdmin(['email' => 'sidebar-admin@undira.ac.id']);

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([
            'Peminjaman Inventaris',
            'Peminjaman Ruangan',
            'Peminjaman Kendaraan Operasional',
            'Riwayat Approval Inventaris',
            'Riwayat Approval Ruangan',
            'Riwayat Approval Kendaraan',
            'Master Kendaraan',
            'Riwayat Peminjaman',
            'Laporan Inventaris',
            'Laporan Peminjaman',
            'Laporan Peminjaman Inventaris',
            'Laporan Peminjaman Ruangan',
            'Laporan Peminjaman Kendaraan',
            'Laporan Maintenance',
        ] as $label) {
            $this->assertStringContainsString('<p>'.$label.'</p>', $html);
        }

        $this->assertStringContainsString('<p>Peminjaman</p>', $html);
        $this->assertStringContainsString('<p>Maintenance</p>', $html);
        $this->assertStringContainsString('<p>Operasional Aset</p>', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<p>[^<]*<i class="fas fa-angle-left right"/',
            $html
        );
        $this->assertStringNotContainsString(route('admin.users.index'), $html);

        /* Collapsed float panel must restore AdminLTE-hidden labels. */
        $layoutCss = file_get_contents(public_path('css/undira-layout.css'));
        $this->assertNotFalse($layoutCss);
        $this->assertStringContainsString('nav-item.is-float-open .nav-treeview .nav-link > p', $layoutCss);
        $this->assertStringContainsString('visibility: visible !important', $layoutCss);
        $this->assertStringContainsString('animation-name: none !important', $layoutCss);
        $this->assertStringContainsString('sidebar-no-expand', $html);
        $this->assertStringContainsString('undira-sidebar-tooltip', $html);
    }

    public function test_viewer_sidebar_stays_limited_to_allowed_borrowing_links(): void
    {
        $viewer = $this->makeViewer(['email' => 'sidebar-viewer@undira.ac.id']);

        $html = $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<p>Pinjam Inventaris</p>', $html);
        $this->assertStringContainsString('<p>Pinjam Ruangan</p>', $html);
        $this->assertStringContainsString('<p>Pinjam Kendaraan</p>', $html);
        $this->assertStringContainsString('<p>Riwayat Peminjaman</p>', $html);
        $this->assertStringNotContainsString('Riwayat Approval', $html);
        $this->assertStringNotContainsString('Master Kendaraan', $html);
        $this->assertStringNotContainsString('Approval Inventaris', $html);
        $this->assertStringNotContainsString(route('admin.users.index'), $html);
        $this->assertStringNotContainsString(route('maintenances.index'), $html);
    }

    public function test_active_state_follows_the_open_borrowing_page(): void
    {
        $admin = $this->makeAdmin(['email' => 'sidebar-active@undira.ac.id']);

        $history = $this->actingAs($admin)->get(route('borrowing-approvals.index'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($history, route('borrowing-approvals.index')));
        $this->assertStringNotContainsString('active', $this->linkClass($history, route('room-borrowings.history')));
        $this->assertStringNotContainsString('active', $this->linkClass($history, route('vehicle-borrowings.history')));
        $this->assertMatchesRegularExpression(
            '/<li class="nav-item menu-open">\s*<a href="#" class="nav-link">\s*<i class="nav-icon fas fa-box">/',
            $history
        );

        $rooms = $this->actingAs($admin)->get(route('room-borrowings.history'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($rooms, route('room-borrowings.history')));
        $this->assertStringNotContainsString('active', $this->linkClass($rooms, route('borrowing-approvals.index')));
        $this->assertMatchesRegularExpression(
            '/<li class="nav-item menu-open">\s*<a href="#" class="nav-link">\s*<i class="nav-icon fas fa-door-open">/',
            $rooms
        );

        $vehicles = $this->actingAs($admin)->get(route('vehicle-borrowings.approvals'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($vehicles, route('vehicle-borrowings.approvals')));
        $this->assertStringNotContainsString('active', $this->linkClass($vehicles, route('vehicle-borrowings.history')));
        $this->assertStringNotContainsString('active', $this->linkClass($vehicles, route('vehicle-fleet.index')));
        $this->assertMatchesRegularExpression(
            '/<li class="nav-item menu-open">\s*<a href="#" class="nav-link">\s*<i class="nav-icon fas fa-car">/',
            $vehicles
        );

        $fleet = $this->actingAs($admin)->get(route('vehicle-fleet.index'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($fleet, route('vehicle-fleet.index')));
        $this->assertStringNotContainsString('active', $this->linkClass($fleet, route('vehicle-borrowings.approvals')));

        $records = $this->actingAs($admin)->get(route('borrowing-records.index'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($records, route('borrowing-records.index')));
        $this->assertStringNotContainsString('active', $this->linkClass($records, route('borrowing-approvals.index')));

        $reports = $this->actingAs($admin)->get(route('reports.index'))->assertOk()->getContent();
        $this->assertStringContainsString('active', $this->linkClass($reports, route('reports.index')));
        $this->assertStringNotContainsString('active', $this->linkClass($reports, route('reports.show', 'dipinjam')));
        $this->assertStringNotContainsString('active', $this->linkClass($reports, route('reports.show', 'peminjaman-ruangan')));
        $this->assertStringNotContainsString('active', $this->linkClass($reports, route('reports.show', 'peminjaman-kendaraan')));

        $roomReport = $this->actingAs($admin)->get(route('reports.show', 'peminjaman-ruangan'))->assertOk()->getContent();
        $this->assertStringContainsString('menu-open', $roomReport);
        $this->assertStringContainsString('active', $this->linkClass($roomReport, route('reports.show', 'peminjaman-ruangan')));
        $this->assertStringNotContainsString('active', $this->linkClass($roomReport, route('reports.show', 'dipinjam')));
        $this->assertStringNotContainsString('active', $this->linkClass($roomReport, route('reports.show', 'peminjaman-kendaraan')));
    }

    private function linkClass(string $html, string $url): string
    {
        $pattern = '/<a href="'.preg_quote($url, '/').'" class="([^"]*)"/';
        $this->assertSame(1, preg_match($pattern, $html, $matches), 'Missing sidebar link for '.$url);

        return $matches[1];
    }
}
