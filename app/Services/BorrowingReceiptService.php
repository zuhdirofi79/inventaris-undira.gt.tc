<?php

namespace App\Services;

use App\Models\Borrowing;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BorrowingReceiptService
{
    public function approvalPublicUrl(Borrowing $borrowing): ?string
    {
        if (! $borrowing->approval_number) {
            return null;
        }

        return URL::route('borrowing.approval.public', [
            'approvalNumber' => $borrowing->approval_number,
        ]);
    }

    /**
     * SVG QR payload pointing to the public approval page.
     */
    public function qrSvg(Borrowing $borrowing, int $size = 180): ?string
    {
        $url = $this->approvalPublicUrl($borrowing);
        if ($url === null) {
            return null;
        }

        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->generate($url);
    }

    public function qrDataUri(Borrowing $borrowing, int $size = 180): ?string
    {
        $svg = $this->qrSvg($borrowing, $size);
        if ($svg === null) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptPayload(Borrowing $borrowing): array
    {
        $borrowing->loadMissing([
            'items.inventoryItem.category',
            'items.inventoryItem.subCategory',
            'items.inventoryItem.room.floor.building.campus',
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room.floor.building.campus',
            'approvedBy',
            'requestedBy',
        ]);

        $item = $borrowing->inventoryItem
            ?? $borrowing->items->first()?->inventoryItem;
        $logoPath = public_path('images/logo-undira.png');
        if (! is_file($logoPath)) {
            $logoPath = public_path('images/undira.png');
        }

        $logoDataUri = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return [
            'borrowing' => $borrowing,
            'item' => $item,
            'items' => $borrowing->items,
            'logoDataUri' => $logoDataUri,
            'qrDataUri' => $this->qrDataUri($borrowing),
            'approvalUrl' => $this->approvalPublicUrl($borrowing),
        ];
    }
}
