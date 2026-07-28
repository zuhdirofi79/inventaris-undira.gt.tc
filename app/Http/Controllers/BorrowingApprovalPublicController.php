<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Services\BorrowingReceiptService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class BorrowingApprovalPublicController extends Controller
{
    public function __construct(
        protected BorrowingReceiptService $receipts,
    ) {}

    public function show(string $approvalNumber): View
    {
        $borrowing = $this->findByApprovalNumber($approvalNumber);

        $borrowing->load([
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room.floor.building.campus',
            'approvedBy',
            'requestedBy',
        ]);

        return view('operations.borrowings.approval-public', [
            'borrowing' => $borrowing,
            'item' => $borrowing->inventoryItem,
            'qrDataUri' => $this->receipts->qrDataUri($borrowing),
            'approvalUrl' => $this->receipts->approvalPublicUrl($borrowing),
        ]);
    }

    public function receipt(string $approvalNumber): View
    {
        $borrowing = $this->findByApprovalNumber($approvalNumber);

        return view('operations.borrowings.receipt', [
            ...$this->receipts->receiptPayload($borrowing),
            'isPdf' => false,
            'autoPrint' => false,
        ]);
    }

    public function receiptPdf(string $approvalNumber): Response
    {
        $borrowing = $this->findByApprovalNumber($approvalNumber);
        $payload = $this->receipts->receiptPayload($borrowing);
        $payload['isPdf'] = true;
        $payload['autoPrint'] = false;

        $filename = 'receipt_'.$borrowing->approval_number.'.pdf';

        return Pdf::loadView('operations.borrowings.receipt', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    protected function findByApprovalNumber(string $approvalNumber): Borrowing
    {
        return Borrowing::query()
            ->where('approval_number', $approvalNumber)
            ->firstOrFail();
    }
}
