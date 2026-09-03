<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Borrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'inventoryItem.category',
            'items.inventoryItem.category',
        ]);
    }

    public function envelope(): Envelope
    {
        $this->borrowing->loadMissing('items');
        $counts = $this->borrowing->itemDecisionCounts();
        $number = $this->borrowing->approval_number;
        $isPartial = $counts['approved'] > 0 && $counts['rejected'] > 0;

        if ($isPartial) {
            $subject = $number
                ? "Hasil Persetujuan Peminjaman (Sebagian) - {$number}"
                : 'Hasil Persetujuan Peminjaman (Sebagian)';
        } else {
            $subject = $number
                ? "Pengajuan Peminjaman Inventaris Disetujui - {$number}"
                : 'Pengajuan Peminjaman Inventaris Disetujui';
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $items = $this->borrowing->lineItems();

        return new Content(
            markdown: 'emails.borrowing-approved',
            with: [
                'borrowing' => $this->borrowing,
                'item' => $this->borrowing->inventoryItem,
                'items' => $items,
            ],
        );
    }
}
