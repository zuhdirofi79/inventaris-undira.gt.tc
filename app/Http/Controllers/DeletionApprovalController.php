<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\DeletionApprovalStatus;
use App\Http\Requests\DeletionApprovalRequest;
use App\Models\DeletionApproval;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeletionApprovalController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected NotificationService $notifications,
    ) {}

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
        $approval = DeletionApproval::query()->create([
            'inventory_item_id' => $request->input('inventory_item_id'),
            'requested_by' => $request->user()->id,
            'reason' => $request->input('reason'),
            'status' => DeletionApprovalStatus::Pending,
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $approval,
            null,
            $approval->only(['inventory_item_id', 'reason', 'status']),
            'DELETION_APPROVAL_REQUESTED',
            $request->input('reason'),
        );

        $this->notifications->notifyDeletionApproval($approval->load('inventoryItem'), 'requested');

        return back()->with('success', 'Permintaan penghapusan berhasil diajukan.');
    }

    public function approve(Request $request, DeletionApproval $deletionApproval): RedirectResponse
    {
        abort_unless($deletionApproval->status === DeletionApprovalStatus::Pending, 422, 'Permintaan sudah diproses.');

        DB::transaction(function () use ($request, $deletionApproval) {
            $deletionApproval->update([
                'status' => DeletionApprovalStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $deletionApproval->inventoryItem?->delete();
        });

        $deletionApproval->refresh()->load(['inventoryItem', 'requestedBy']);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $deletionApproval,
            ['status' => DeletionApprovalStatus::Pending->value],
            ['status' => DeletionApprovalStatus::Approved->value],
            'DELETION_APPROVAL_APPROVED',
        );

        $this->notifications->notifyDeletionApproval($deletionApproval, 'approved');

        return back()->with('success', 'Penghapusan disetujui.');
    }

    public function reject(Request $request, DeletionApproval $deletionApproval): RedirectResponse
    {
        abort_unless($deletionApproval->status === DeletionApprovalStatus::Pending, 422, 'Permintaan sudah diproses.');

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $deletionApproval->update([
            'status' => DeletionApprovalStatus::Rejected,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $deletionApproval,
            ['status' => DeletionApprovalStatus::Pending->value],
            [
                'status' => DeletionApprovalStatus::Rejected->value,
                'rejection_reason' => $request->input('rejection_reason'),
            ],
            'DELETION_APPROVAL_REJECTED',
            $request->input('rejection_reason'),
        );

        $this->notifications->notifyDeletionApproval(
            $deletionApproval->load(['inventoryItem', 'requestedBy']),
            'rejected',
        );

        return back()->with('success', 'Penghapusan ditolak.');
    }
}
