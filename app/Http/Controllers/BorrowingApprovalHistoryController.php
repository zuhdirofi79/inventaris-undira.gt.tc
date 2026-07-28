<?php

namespace App\Http\Controllers;

use App\Enums\BorrowingStatus;
use App\Exports\EamFlatReportExport;
use App\Models\Borrowing;
use App\Services\BorrowingReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BorrowingApprovalHistoryController extends Controller
{
    public function __construct(
        protected BorrowingReceiptService $receipts,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeHistory($request);

        $borrowings = $this->filteredQuery($request)
            ->paginate(20)
            ->withQueryString();

        return view('operations.borrowings.approval-history', [
            'borrowings' => $borrowings,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'statuses' => BorrowingStatus::cases(),
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $this->authorizeHistory($request);
        $rows = $this->filteredQuery($request)->limit(2000)->get();

        return Pdf::loadView('exports.borrowing-approval-history-pdf', [
            'rows' => $rows,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->download(
            'riwayat_approval_'.now()->format('Ymd_His').'.pdf'
        );
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeHistory($request);
        $rows = $this->filteredQuery($request)->limit(5000)->get();

        $excelRows = $rows->map(function (Borrowing $b, int $i) {
            $count = $b->items->count() ?: ($b->inventoryItem ? 1 : 0);
            $names = $b->items->map(fn ($l) => $l->inventoryItem?->name)->filter()->implode(', ');
            $numbers = $b->items->map(fn ($l) => $l->inventoryItem?->inventory_number)->filter()->implode(', ');
            if ($names === '') {
                $names = $b->inventoryItem?->name;
                $numbers = $b->inventoryItem?->inventory_number;
            }

            return [
                $i + 1,
                $b->approval_number ?? '—',
                $b->approved_at?->format('Y-m-d H:i') ?? $b->rejected_at?->format('Y-m-d H:i') ?? '—',
                $b->borrower_name,
                $names,
                $numbers,
                $count,
                $b->status?->label(),
                $b->approvedBy?->name ?? '—',
                $b->approved_at?->format('Y-m-d H:i') ?? '—',
            ];
        })->all();

        $export = new EamFlatReportExport(
            ['No', 'Nomor Approval', 'Tanggal', 'Nama User', 'Barang', 'No Inventaris', 'Jml Item', 'Status', 'Approved By', 'Approved At'],
            $excelRows,
            'Riwayat Approval'
        );

        return Excel::download($export, 'riwayat_approval_'.now()->format('Ymd_His').'.xlsx');
    }

    protected function authorizeHistory(Request $request): void
    {
        abort_unless(
            $request->user()?->can('borrowing.approve') ?? false,
            403
        );
    }

    /**
     * @return Builder<Borrowing>
     */
    protected function filteredQuery(Request $request)
    {
        return Borrowing::query()
            ->with([
                'inventoryItem:id,inventory_number,name,category_id,sub_category_id,room_id',
                'items.inventoryItem:id,inventory_number,name',
                'approvedBy:id,name',
                'requestedBy:id,name',
            ])
            ->where(function ($q) {
                $q->whereNotNull('approval_number')
                    ->orWhereIn('status', [
                        BorrowingStatus::Disetujui->value,
                        BorrowingStatus::Ditolak->value,
                        BorrowingStatus::Dipinjam->value,
                        BorrowingStatus::Dikembalikan->value,
                        BorrowingStatus::Terlambat->value,
                    ]);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = (string) $request->input('search');
                $q->where(function ($inner) use ($term) {
                    $inner->where('approval_number', 'like', "%{$term}%")
                        ->orWhere('borrower_name', 'like', "%{$term}%")
                        ->orWhere('borrower_email', 'like', "%{$term}%")
                        ->orWhereHas('inventoryItem', function ($i) use ($term) {
                            $i->where('name', 'like', "%{$term}%")
                                ->orWhere('inventory_number', 'like', "%{$term}%");
                        })
                        ->orWhereHas('items.inventoryItem', function ($i) use ($term) {
                            $i->where('name', 'like', "%{$term}%")
                                ->orWhere('inventory_number', 'like', "%{$term}%");
                        })
                        ->orWhereHas('approvedBy', fn ($u) => $u->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from') || $request->filled('date_to'), function ($q) use ($request) {
                $q->where(function ($outer) use ($request) {
                    $outer->where(function ($approved) use ($request) {
                        $approved->whereNotNull('approved_at');
                        if ($request->filled('date_from')) {
                            $approved->whereDate('approved_at', '>=', $request->input('date_from'));
                        }
                        if ($request->filled('date_to')) {
                            $approved->whereDate('approved_at', '<=', $request->input('date_to'));
                        }
                    })->orWhere(function ($rejected) use ($request) {
                        $rejected->whereNotNull('rejected_at');
                        if ($request->filled('date_from')) {
                            $rejected->whereDate('rejected_at', '>=', $request->input('date_from'));
                        }
                        if ($request->filled('date_to')) {
                            $rejected->whereDate('rejected_at', '<=', $request->input('date_to'));
                        }
                    });
                });
            })
            ->latest('approved_at')
            ->latest('rejected_at')
            ->latest('id');
    }
}
