<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InventoryPhotoController extends Controller
{
    public function store(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:5120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        foreach ($request->file('photos') as $index => $photo) {
            $path = $photo->store("inventory/photos/{$inventory->id}", 'public');

            $isPrimary = $request->boolean('is_primary') && $index === 0;

            if ($isPrimary) {
                $inventory->photos()->update(['is_primary' => false]);
            }

            $inventory->photos()->create([
                'path' => $path,
                'is_primary' => $isPrimary || ($inventory->photos()->count() === 0 && $index === 0),
            ]);
        }

        return back()->with('success', 'Foto berhasil diupload.');
    }

    public function destroy(InventoryItem $inventory, InventoryPhoto $photo): RedirectResponse
    {
        abort_unless($photo->inventory_item_id === $inventory->id, 404);

        Storage::disk('public')->delete($photo->path);
        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $next = $inventory->photos()->first();
            $next?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
