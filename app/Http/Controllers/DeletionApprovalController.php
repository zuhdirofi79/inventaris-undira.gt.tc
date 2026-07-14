<?php

namespace App\Http\Controllers;

use App\Enums\DeletionApprovalStatus;
use App\Http\Requests\DeletionApprovalRequest;
use App\Models\DeletionApproval;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeletionApprovalController extends Controller
{
    public function index(): View
    {
        $approvals = DeletionApproval::query()
            ->with(['inventoryItem', 'requestedBy', 'approvedBy'])
            ->latest()
            ->paginate(20);

        return view('operations.deletion-approvals.index', compact('approvals'));
    }

    public function store(DeletionApprovalRequest $request): RedirectResponse
    {
        DeletionApproval::query()->create([
            'inventory_item_id' => $request->input('inventory_item_id'),
            'requested_by' => $request->user()->id,
            'reason' => $request->input('reason'),
            'status' => DeletionApprovalStatus::Pending,
        ]);

        return back()->with('success', 'Permintaan penghapusan berhasil diajukan.');
    }

    public function approve(Request $request, DeletionApproval $deletionApproval): RedirectResponse
    {
        DB::transaction(function () use ($request, $deletionApproval) {
            $deletionApproval->update([
                'status' => DeletionApprovalStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $deletionApproval->inventoryItem?->delete();
        });

        return back()->with('success', 'Penghapusan disetujui.');
    }

    public function reject(Request $request, DeletionApproval $deletionApproval): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $deletionApproval->update([
            'status' => DeletionApprovalStatus::Rejected,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return back()->with('success', 'Penghapusan ditolak.');
    }
}
