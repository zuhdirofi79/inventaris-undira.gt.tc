<?php

namespace App\Http\Controllers;

use App\Enums\FacilityRequestStatus;
use App\Http\Requests\DecideVehicleBorrowingRequest;
use App\Http\Requests\StoreVehicleBorrowingRequest;
use App\Models\Campus;
use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Policies\BorrowingPolicy;
use App\Services\VehicleBorrowingService;
use App\Support\CampusQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleBorrowingController extends Controller
{
    public function __construct(
        private readonly VehicleBorrowingService $service,
    ) {}

    public function create(): View
    {
        return view('operations.vehicle-borrowings.create', [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'routes' => OperationalRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreVehicleBorrowingRequest $request): RedirectResponse
    {
        $borrowing = $this->service->submit(
            $request->user(),
            $request->safe()->except(['supporting_document']),
            $request->file('supporting_document'),
        );

        return redirect()
            ->route('vehicle-borrowings.show', $borrowing)
            ->with('success', 'Peminjaman kendaraan berhasil diajukan dan menunggu persetujuan.');
    }

    public function approvals(Request $request): View
    {
        return $this->listing($request, 'approvals');
    }

    public function history(Request $request): View
    {
        return $this->listing($request, 'history');
    }

    public function show(Request $request, VehicleBorrowing $vehicleBorrowing): View
    {
        $this->authorizeVisible($request->user(), $vehicleBorrowing);
        $vehicleBorrowing->load(['campus', 'route', 'vehicle', 'driver', 'approvedBy', 'requestedBy']);

        return view('operations.vehicle-borrowings.show', [
            'borrowing' => $vehicleBorrowing,
            'vehicles' => OperationalVehicle::query()->where('is_active', true)->orderBy('name')->get(),
            'drivers' => OperationalDriver::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function decide(DecideVehicleBorrowingRequest $request, VehicleBorrowing $vehicleBorrowing): RedirectResponse
    {
        $saved = $this->service->decide($request->user(), $vehicleBorrowing, $request->validated());
        $message = $saved->status === FacilityRequestStatus::Ditolak
            ? 'Pengajuan kendaraan ditolak.'
            : 'Pengajuan kendaraan disetujui.';

        return redirect()
            ->route('vehicle-borrowings.show', $saved)
            ->with('success', $message);
    }

    public function downloadDocument(Request $request, VehicleBorrowing $vehicleBorrowing): StreamedResponse
    {
        $this->authorizeVisible($request->user(), $vehicleBorrowing);
        $path = $vehicleBorrowing->supporting_document_path;
        abort_unless(is_string($path) && $path !== '' && Storage::disk(VehicleBorrowingService::DISK)->exists($path), 404);

        return Storage::disk(VehicleBorrowingService::DISK)->download(
            $path,
            $vehicleBorrowing->supporting_document_original_name ?: basename($path),
        );
    }

    private function listing(Request $request, string $mode): View
    {
        $campusId = CampusQuery::id($request->input('campus_id'));
        $search = str_replace(['%', '_'], '', trim((string) $request->input('q', '')));
        $status = trim((string) $request->input('status', ''));

        $borrowings = VehicleBorrowing::query()
            ->with(['campus:id,code,name', 'route:id,name', 'vehicle:id,name,plate_number', 'driver:id,name', 'approvedBy:id,name'])
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when(is_int($campusId) && $campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('origin', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhereHas('route', fn ($route) => $route->where('name', 'like', $like))
                        ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('name', 'like', $like)->orWhere('plate_number', 'like', $like));
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

        return view('operations.vehicle-borrowings.index', [
            'borrowings' => $borrowings,
            'mode' => $mode,
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'code', 'name']),
            'campusId' => is_int($campusId) ? $campusId : '',
            'search' => $search,
            'status' => $status,
        ]);
    }

    private function authorizeVisible(?User $user, VehicleBorrowing $borrowing): void
    {
        abort_unless($user && ($user->can('vehicle-borrowing.view') || $user->can('vehicle-borrowing.approve')), 403);

        if (app(BorrowingPolicy::class)->limitsToOwnRecords($user) && (int) $borrowing->requested_by !== (int) $user->id) {
            abort(403);
        }
    }
}
