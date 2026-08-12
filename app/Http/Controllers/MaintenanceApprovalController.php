<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Http\Requests\ApproveMaintenanceRequest;
use App\Http\Requests\RejectMaintenanceRequest;
use App\Models\Maintenance;
use App\Services\MaintenanceWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaintenanceApprovalController extends Controller
{
    public function __construct(
        protected MaintenanceWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        $maintenances = Maintenance::query()
            ->with(['inventoryItem.room.floor.building.campus', 'technicianUser', 'creator'])
            ->where('status', MaintenanceStatus::PendingApproval)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('maintenance_number', 'like', $term)
                        ->orWhere('technician', 'like', $term)
                        ->orWhereHas('inventoryItem', function ($item) use ($term) {
                            $item->where('inventory_number', 'like', $term)
                                ->orWhere('name', 'like', $term);
                        });
                });
            })
            ->latest('submitted_for_approval_at')
            ->paginate(20)
            ->withQueryString();

        return view('operations.maintenance-approvals.index', compact('maintenances'));
    }

    public function approve(ApproveMaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        try {
            $this->workflow->approve($maintenance, $request->user(), $request->input('approval_note'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenance-approvals.index')
            ->with('success', 'Maintenance disetujui. Status inventaris menjadi Aktif.');
    }

    public function reject(RejectMaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        try {
            $this->workflow->reject($maintenance, $request->user(), $request->string('rejection_reason')->toString());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenance-approvals.index')
            ->with('success', 'Work Order berhasil dikembalikan untuk revisi perbaikan.');
    }
}
