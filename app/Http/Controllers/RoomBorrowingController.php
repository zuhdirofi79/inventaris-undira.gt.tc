<?php

namespace App\Http\Controllers;

use App\Enums\FacilityRequestStatus;
use App\Enums\RoomActivityType;
use App\Http\Requests\DecideRoomBorrowingRequest;
use App\Http\Requests\StoreRoomBorrowingRequest;
use App\Models\Campus;
use App\Models\RoomBorrowing;
use App\Models\User;
use App\Policies\BorrowingPolicy;
use App\Services\RoomBorrowingService;
use App\Support\CampusQuery;
use App\Support\RoomBorrowingTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomBorrowingController extends Controller
{
    public function __construct(
        private readonly RoomBorrowingService $service,
        private readonly RoomBorrowingTemplates $templates,
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
            'participant_external',
            'approval_document',
            'layout_document',
            'participant_document',
        ]);

        return view('operations.room-borrowings.create', [
            'activityTypes' => RoomActivityType::cases(),
            'templates' => $this->templates->links(),
        ]);
    }

    public function store(StoreRoomBorrowingRequest $request): RedirectResponse
    {
        $borrowing = $this->service->submit(
            $request->user(),
            $request->safe()->except(['approval_document', 'layout_document', 'participant_document']),
            $request->file('approval_document'),
            $request->file('layout_document'),
            $request->file('participant_document'),
        );

        return redirect()
            ->route('room-borrowings.show', $borrowing)
            ->with('success', 'Peminjaman ruangan berhasil diajukan dan menunggu persetujuan.');
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

    public function downloadDocument(Request $request, RoomBorrowing $roomBorrowing, string $kind): StreamedResponse
    {
        $this->authorizeVisible($request->user(), $roomBorrowing);
        $meta = $roomBorrowing->documentMeta($kind);
        abort_unless($meta && Storage::disk(RoomBorrowingService::DISK)->exists($meta['path']), 404);

        return Storage::disk(RoomBorrowingService::DISK)->download($meta['path'], $meta['name']);
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
            ->tap(function ($q) use ($mode, $status) {
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
        ]);
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
