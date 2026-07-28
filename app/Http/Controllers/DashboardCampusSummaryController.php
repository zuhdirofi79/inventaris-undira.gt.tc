<?php

namespace App\Http\Controllers;

use App\Services\DashboardCampusExecutiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modular AJAX endpoints for the executive campus summary section.
 * Does not alter existing DashboardController routes/actions.
 */
class DashboardCampusSummaryController extends Controller
{
    public function __construct(
        protected DashboardCampusExecutiveService $executive,
    ) {}

    public function partial(): View
    {
        $summary = $this->executive->summary();

        return view('dashboard.partials.campus-executive-section', [
            'executiveSummary' => $summary,
            'canRefresh' => $this->canRefresh(),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        abort_unless($this->canRefresh(), 403);

        $summary = $this->executive->summary(forceRefresh: true);
        $html = view('dashboard.partials.campus-executive-grid', [
            'executiveSummary' => $summary,
        ])->render();

        return response()->json([
            'ok' => true,
            'generated_at' => $summary['generated_at'],
            'html' => $html,
            'message' => 'Data ringkasan inventaris berhasil diperbarui.',
        ]);
    }

    protected function canRefresh(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasAnyRole(['super-admin', 'admin']));
    }
}
