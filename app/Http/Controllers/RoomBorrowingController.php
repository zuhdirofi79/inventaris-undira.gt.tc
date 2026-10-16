<?php

namespace App\Http\Controllers;

use App\Enums\FacilityRequestStatus;
use App\Enums\RoomActivityType;
use App\Http\Requests\DecideRoomBorrowingRequest;
use App\Http\Requests\StoreRoomBorrowingRequest;
use App\Models\Campus;
use App\Models\RoomBorrowing;
use App\Models\User;
use App\Http\Controllers\Concerns\RespondsToAjaxForm;
use App\Policies\BorrowingPolicy;
use App\Services\FacilityReceiptService;
use App\Services\RoomBorrowingService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\CampusQuery;
use App\Support\DateRangeQuery;
use App\Support\PrivateDocumentResponse;
use App\Support\RoomBorrowingTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomBorrowingController extends Controller
{
    use RespondsToAjaxForm;

    public function __construct(
        private readonly RoomBorrowingService $service,
        private readonly RoomBorrowingTemplates $templates,
        private readonly FacilityReceiptService $receipts,
    ) {}

    public function create(): View
    {
        view()->share('inlineValidationFields', [
            'activity_type',
            'purpose',
            'room_id',
            'borrower_name',
            'borrower_email',
            'borrower_phone',
            'starts_at',
            'ends_at',
            'documentation_url',
            'uses_budget',
            'budget_type',
            'participants',
            'participant_internal',
            'participant_internal_count',
            'participant_external',
            'participant_external_count',
            'approval_document',
            'layout_document',
            'participant_document',
        ]);

        return view('operations.room-borrowings.create', [
            'activityTypes' => RoomActivityType::cases(),
            'templates' => $this->templates->links(),
        ]);
    }

    public function store(StoreRoomBorrowingRequest $request): RedirectResponse|JsonResponse
    {
        $borrowing = $this->service->submit(
            $request->user(),
            $request->safe()->except(['approval_document', 'layout_document', 'participant_document']),
            $request->file('approval_document'),
            $request->file('layout_document'),
            $request->file('participant_document'),
        );

        return $this->formSuccess(
            $request,
            route('room-borrowings.show', $borrowing),
            'Peminjaman ruangan berhasil diajukan dan menunggu persetujuan.',
        );
    }

    public function approvals(Request $request): View
    {
        return $this->listing($request, 'approvals');
    }

    public function history(Request $request): View
    {
        return $this->listing($request, 'history');
    }

    public function show(Request $request, RoomBorrowing $roomBorrowing): View
    {
        $this->authorizeVisible($request->user(), $roomBorrowing);
        $roomBorrowing->load([
            'room.floor.building.campus',
            'room.inventoryItems',
            'campus',
            'approvedBy',
            'requestedBy',
        ]);

        return view('operations.room-borrowings.show', [
            'borrowing' => $roomBorrowing,
        ]);
    }

    public function decide(DecideRoomBorrowingRequest $request, RoomBorrowing $roomBorrowing): RedirectResponse
    {
        $saved = $this->service->decide($request->user(), $roomBorrowing, $request->validated());
        $message = $saved->status === FacilityRequestStatus::Ditolak
            ? 'Pengajuan ruangan ditolak.'
            : 'Pengajuan ruangan disetujui.';

        return redirect()
            ->route('room-borrowings.show', $saved)
            ->with('success', $message);
    }

    public function approval(Request $request, RoomBorrowing $roomBorrowing): View
    {
        $this->authorizeDocument($request, $roomBorrowing);

        return view('operations.facility.approval-detail', $this->receipts->room($roomBorrowing));
    }

    public function receipt(Request $request, RoomBorrowing $roomBorrowing): View
    {
        $this->authorizeDocument($request, $roomBorrowing);

        return view('operations.facility.receipt', [
            ...$this->receipts->room($roomBorrowing),
            'isPdf' => false,
        ]);
    }

    public function receiptPdf(Request $request, RoomBorrowing $roomBorrowing): Response
    {
        $this->authorizeDocument($request, $roomBorrowing);
        $payload = $this->receipts->room($roomBorrowing);
        $payload['isPdf'] = true;

        return Pdf::loadView('operations.facility.receipt', $payload)
            ->setPaper('a4', 'portrait')
            ->download('receipt_'.$roomBorrowing->approval_number.'.pdf');
    }

    public function viewDocument(Request $request, RoomBorrowing $roomBorrowing, string $kind): Response
    {
        return $this->documentResponse($request, $roomBorrowing, $kind, false);
    }

    public function downloadDocument(Request $request, RoomBorrowing $roomBorrowing, string $kind): Response
    {
        return $this->documentResponse($request, $roomBorrowing, $kind, true);
    }

    private function documentResponse(Request $request, RoomBorrowing $roomBorrowing, string $kind, bool $download): Response
    {
        $this->authorizeVisible($request->user(), $roomBorrowing);
        $meta = $roomBorrowing->documentMeta($kind);
        abort_unless($meta, 404, 'Dokumen pendukung tidak ditemukan.');

        return PrivateDocumentResponse::stream(
            RoomBorrowingService::DISK,
            $meta['path'],
            'room-borrowings/'.$roomBorrowing->id,
            $meta['name'],
            $download,
            'room-borrowing:'.$roomBorrowing->id.':'.$kind,
        );
    }

    public function downloadTemplate(string $kind): StreamedResponse
    {
        $path = $this->templates->find($kind);
        abort_unless(is_string($path), 404);

        return Storage::disk(RoomBorrowingTemplates::DISK)->download($path, basename($path));
    }

    private function listing(Request $request, string $mode): View
    {
        $campusId = CampusQuery::id($request->input('campus_id'));
        $search = $this->searchTerm($request);
        $status = trim((string) $request->input('status', ''));
        $dates = $mode === 'history'
            ? DateRangeQuery::validated($request)
            : ['date_from' => null, 'date_to' => null];

        $borrowings = RoomBorrowing::query()
            ->with(['room:id,floor_id,code,name', 'campus:id,code,name', 'approvedBy:id,name'])
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when(is_int($campusId) && $campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('activity_type', 'like', $like)
                        ->orWhereHas('room', fn ($room) => $room->where('name', 'like', $like)->orWhere('code', 'like', $like));
                });
            })
            ->tap(function ($q) use ($mode, $status, $dates) {
                if ($mode === 'approvals') {
                    $q->where('status', FacilityRequestStatus::MenungguPersetujuan->value);

                    return;
                }

                $q->where('status', '!=', FacilityRequestStatus::MenungguPersetujuan->value);
                if ($status === 'selesai') {
                    $q->where('status', FacilityRequestStatus::Disetujui->value)->where('ends_at', '<', now());
                } elseif (in_array($status, [FacilityRequestStatus::Disetujui->value, FacilityRequestStatus::Ditolak->value], true)) {
                    $q->where('status', $status);
                }

                DateRangeQuery::applyDecisionDates($q, $dates['date_from'], $dates['date_to']);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('operations.room-borrowings.index', [
            'borrowings' => $borrowings,
            'mode' => $mode,
            'campuses' => $this->campusOptions(),
            'campusId' => is_int($campusId) ? $campusId : '',
            'search' => $search,
            'status' => $status,
            'dateFrom' => $dates['date_from'] ?? '',
            'dateTo' => $dates['date_to'] ?? '',
        ]);
    }

    private function authorizeDocument(Request $request, RoomBorrowing $borrowing): void
    {
        $this->authorizeVisible($request->user(), $borrowing);
        abort_unless(filled($borrowing->approval_number), 404);
    }

    private function authorizeVisible(?User $user, RoomBorrowing $borrowing): void
    {
        abort_unless($user && ($user->can('room-borrowing.view') || $user->can('room-borrowing.approve')), 403);

        if (app(BorrowingPolicy::class)->limitsToOwnRecords($user) && (int) $borrowing->requested_by !== (int) $user->id) {
            abort(403);
        }
    }

    private function searchTerm(Request $request): string
    {
        return str_replace(['%', '_'], '', trim((string) $request->input('q', '')));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Campus>
     */
    private function campusOptions()
    {
        return Campus::query()->orderBy('name')->get(['id', 'code', 'name']);
    }
}
