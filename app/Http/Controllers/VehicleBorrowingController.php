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
use App\Http\Controllers\Concerns\RespondsToAjaxForm;
use App\Services\FacilityReceiptService;
use App\Services\VehicleBorrowingService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\CampusQuery;
use App\Support\DateRangeQuery;
use App\Support\PrivateDocumentResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleBorrowingController extends Controller
{
    use RespondsToAjaxForm;

    public function __construct(
        private readonly VehicleBorrowingService $service,
        private readonly FacilityReceiptService $receipts,
    ) {}

    public function create(): View
    {
        view()->share('inlineValidationFields', [
            'borrower_name',
            'borrower_email',
            'borrower_phone',
            'route_id',
            'destination',
            'purpose',
            'service_type',
            'passenger_count',
            'passengers',
            'passengers.*.name',
            'campus_id',
            'starts_at',
            'ends_at',
            'supporting_document',
        ]);

        return view('operations.vehicle-borrowings.create', [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'routes' => OperationalRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreVehicleBorrowingRequest $request): RedirectResponse|JsonResponse
    {
        $borrowing = $this->service->submit(
            $request->user(),
            $request->safe()->except(['supporting_document']),
            $request->file('supporting_document'),
        );

        return $this->formSuccess(
            $request,
            route('vehicle-borrowings.show', $borrowing),
            'Peminjaman kendaraan berhasil diajukan dan menunggu persetujuan.',
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

    public function show(Request $request, VehicleBorrowing $vehicleBorrowing): View
    {
        $this->authorizeVisible($request->user(), $vehicleBorrowing);
        $vehicleBorrowing->load(['campus', 'route', 'vehicle', 'driver', 'approvedBy', 'requestedBy']);

        $conflicts = ['vehicles' => [], 'drivers' => []];
        if (
            $vehicleBorrowing->status === FacilityRequestStatus::MenungguPersetujuan
            && $vehicleBorrowing->starts_at
            && $vehicleBorrowing->ends_at
        ) {
            $conflicts = $this->service->conflictMapForSchedule(
                $vehicleBorrowing->starts_at,
                $vehicleBorrowing->ends_at,
                $vehicleBorrowing->id,
            );
        }

        return view('operations.vehicle-borrowings.show', [
            'borrowing' => $vehicleBorrowing,
            'vehicles' => OperationalVehicle::query()->where('is_active', true)->orderBy('name')->get(),
            'drivers' => OperationalDriver::query()->where('is_active', true)->orderBy('name')->get(),
            'assignmentConflicts' => $conflicts,
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

    public function approval(Request $request, VehicleBorrowing $vehicleBorrowing): View
    {
        $this->authorizeDocument($request, $vehicleBorrowing);

        return view('operations.facility.approval-detail', $this->receipts->vehicle($vehicleBorrowing));
    }

    public function receipt(Request $request, VehicleBorrowing $vehicleBorrowing): View
    {
        $this->authorizeDocument($request, $vehicleBorrowing);

        return view('operations.facility.receipt', [
            ...$this->receipts->vehicle($vehicleBorrowing),
            'isPdf' => false,
        ]);
    }

    public function receiptPdf(Request $request, VehicleBorrowing $vehicleBorrowing): Response
    {
        $this->authorizeDocument($request, $vehicleBorrowing);
        $payload = $this->receipts->vehicle($vehicleBorrowing);
        $payload['isPdf'] = true;

        return Pdf::loadView('operations.facility.receipt', $payload)
            ->setPaper('a4', 'portrait')
            ->download('receipt_'.$vehicleBorrowing->approval_number.'.pdf');
    }

    public function viewDocument(Request $request, VehicleBorrowing $vehicleBorrowing): Response
    {
        return $this->documentResponse($request, $vehicleBorrowing, false);
    }

    public function downloadDocument(Request $request, VehicleBorrowing $vehicleBorrowing): Response
    {
        return $this->documentResponse($request, $vehicleBorrowing, true);
    }

    private function documentResponse(Request $request, VehicleBorrowing $vehicleBorrowing, bool $download): Response
    {
        $this->authorizeVisible($request->user(), $vehicleBorrowing);
        $path = $vehicleBorrowing->supporting_document_path;
        abort_unless(is_string($path) && $path !== '', 404, 'Dokumen pendukung tidak ditemukan.');

        return PrivateDocumentResponse::stream(
            VehicleBorrowingService::DISK,
            $path,
            'vehicle-borrowings/'.$vehicleBorrowing->id,
            $vehicleBorrowing->supporting_document_original_name ?: basename($path),
            $download,
            'vehicle-borrowing:'.$vehicleBorrowing->id,
        );
    }

    private function listing(Request $request, string $mode): View
    {
        $campusId = CampusQuery::id($request->input('campus_id'));
        $search = str_replace(['%', '_'], '', trim((string) $request->input('q', '')));
        $status = trim((string) $request->input('status', ''));
        $dates = $mode === 'history'
            ? DateRangeQuery::validated($request)
            : ['date_from' => null, 'date_to' => null];

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

        return view('operations.vehicle-borrowings.index', [
            'borrowings' => $borrowings,
            'mode' => $mode,
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'code', 'name']),
            'campusId' => is_int($campusId) ? $campusId : '',
            'search' => $search,
            'status' => $status,
            'dateFrom' => $dates['date_from'] ?? '',
            'dateTo' => $dates['date_to'] ?? '',
        ]);
    }

    private function authorizeDocument(Request $request, VehicleBorrowing $borrowing): void
    {
        $this->authorizeVisible($request->user(), $borrowing);
        abort_unless(filled($borrowing->approval_number), 404);
    }

    private function authorizeVisible(?User $user, VehicleBorrowing $borrowing): void
    {
        abort_unless($user && ($user->can('vehicle-borrowing.view') || $user->can('vehicle-borrowing.approve')), 403);

        if (app(BorrowingPolicy::class)->limitsToOwnRecords($user) && (int) $borrowing->requested_by !== (int) $user->id) {
            abort(403);
        }
    }
}
