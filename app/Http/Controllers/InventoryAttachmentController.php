<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreInventoryAttachmentRequest;
use App\Models\InventoryAttachment;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class InventoryAttachmentController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function store(StoreInventoryAttachmentRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store("inventory/attachments/{$inventory->id}", 'public');

        $attachment = $inventory->attachments()->create([
            'type' => $request->validated('type'),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);

        $this->audit->log($request->user(), AuditAction::Update, $inventory, null, [
            'attachment_id' => $attachment->id,
            'type' => $attachment->type,
            'original_name' => $attachment->original_name,
        ], 'INVENTORY_ATTACHMENT_UPLOADED');

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function destroy(InventoryItem $inventory, InventoryAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->inventory_item_id === $inventory->id, 404);

        $meta = [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ];

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        $this->audit->log(request()->user(), AuditAction::Update, $inventory, null, $meta, 'INVENTORY_ATTACHMENT_DELETED');

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }
}
