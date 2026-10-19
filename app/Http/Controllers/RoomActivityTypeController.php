<?php

namespace App\Http\Controllers;

use App\Models\RoomActivityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomActivityTypeController extends Controller
{
    public function index(): View
    {
        return view('operations.room-activity-types.index', [
            'activityTypes' => RoomActivityType::query()
                ->withCount('borrowings')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('room_activity_types', 'name')],
        ], [
            'name.required' => 'Nama jenis kegiatan wajib diisi.',
            'name.unique' => 'Nama jenis kegiatan sudah digunakan.',
            'name.max' => 'Nama jenis kegiatan maksimal 255 karakter.',
        ]);

        $name = trim(strip_tags($data['name']));
        $maxOrder = (int) RoomActivityType::query()->max('sort_order');

        RoomActivityType::query()->create([
            'code' => RoomActivityType::makeUniqueCode($name),
            'name' => $name,
            'is_active' => true,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('room-activity-types.index')
            ->with('success', 'Jenis kegiatan ruangan ditambahkan.');
    }

    public function update(Request $request, RoomActivityType $roomActivityType): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_activity_types', 'name')->ignore($roomActivityType->id),
            ],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ], [
            'name.required' => 'Nama jenis kegiatan wajib diisi.',
            'name.unique' => 'Nama jenis kegiatan sudah digunakan.',
            'name.max' => 'Nama jenis kegiatan maksimal 255 karakter.',
        ]);

        $roomActivityType->update([
            'name' => trim(strip_tags($data['name'])),
            'is_active' => $data['is_active'] === '1',
        ]);

        return redirect()
            ->route('room-activity-types.index')
            ->with('success', 'Jenis kegiatan ruangan diperbarui.');
    }

    public function destroy(RoomActivityType $roomActivityType): RedirectResponse
    {
        if ($roomActivityType->isUsed()) {
            return redirect()
                ->route('room-activity-types.index')
                ->with('error', 'Jenis kegiatan sudah digunakan pada peminjaman dan tidak dapat dihapus. Nonaktifkan saja jika tidak ingin dipilih lagi.');
        }

        $roomActivityType->delete();

        return redirect()
            ->route('room-activity-types.index')
            ->with('success', 'Jenis kegiatan ruangan dihapus.');
    }
}
