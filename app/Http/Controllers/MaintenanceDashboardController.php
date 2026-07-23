<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceDashboardStatsService;
use Illuminate\View\View;

class MaintenanceDashboardController extends Controller
{
    public function __construct(
        protected MaintenanceDashboardStatsService $stats,
    ) {}

    public function index(): View
    {
        abort_unless(request()->user()?->can('maintenance.view') ?? false, 403);

        $data = $this->stats->summary();

        return view('operations.maintenance-dashboard.index', [
            'cards' => $data['cards'],
            'charts' => $data['charts'],
            'tables' => $data['tables'],
            'damaged' => $this->stats->damagedInventoryCounts(),
        ]);
    }
}
