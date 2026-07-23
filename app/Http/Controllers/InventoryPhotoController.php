<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreInventoryPhotoRequest;
use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class InventoryPhotoController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function store(StoreInventoryPhotoRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $uploaded = 0;

        foreach ($request->file('photos') as $index => $photo) {
            $path = $photo->store("inventory/photos/{$inventory->id}", 'public');
            $makePrimary = $request->boolean('is_primary') && $index === 0;

            if ($makePrimary) {
                $inventory->photos()->update(['is_primary' => false]);
            }

            $isFirstPhoto = $inventory->photos()->count() === 0 && $index === 0;

            $inventory->photos()->create([
                'path' => $path,
                'is_primary' => $makePrimary || $isFirstPhoto,
            ]);
            $uploaded++;
        }

        $this->audit->log($request->user(), AuditAction::Update, $inventory, null, [
            'photos_uploaded' => $uploaded,
        ], 'INVENTORY_PHOTOS_UPLOADED');

        return back()->with('success', "{$uploaded} foto berhasil diunggah.");
    }

    public function setPrimary(InventoryItem $inventory, InventoryPhoto $photo): RedirectResponse
    {
        abort_unless($photo->inventory_item_id === $inventory->id, 404);
        abort_unless(request()->user()?->can('inventory.update') ?? false, 403);

        $inventory->photos()->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);

        return back()->with('success', 'Foto utama diperbarui.');
    }

    public function destroy(InventoryItem $inventory, InventoryPhoto $photo): RedirectResponse
    {
        abort_unless($photo->inventory_item_id === $inventory->id, 404);

        $photoId = $photo->id;
        Storage::disk('public')->delete($photo->path);
        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $inventory->photos()->first()?->update(['is_primary' => true]);
        }

        $this->audit->log(request()->user(), AuditAction::Update, $inventory, null, [
            'photo_id' => $photoId,
        ], 'INVENTORY_PHOTO_DELETED');

        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
