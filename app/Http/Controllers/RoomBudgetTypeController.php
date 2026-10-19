<?php

namespace App\Http\Controllers;

use App\Models\RoomBudgetType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomBudgetTypeController extends Controller
{
    public function index(): View
    {
        return view('operations.room-budget-types.index', [
            'budgetTypes' => RoomBudgetType::query()
                ->withCount('borrowings')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('room_budget_types', 'name')],
        ], [
            'name.required' => 'Nama anggaran wajib diisi.',
            'name.unique' => 'Nama anggaran sudah digunakan.',
            'name.max' => 'Nama anggaran maksimal 255 karakter.',
        ]);

        $name = trim(strip_tags($data['name']));
        $maxOrder = (int) RoomBudgetType::query()->max('sort_order');

        RoomBudgetType::query()->create([
            'code' => RoomBudgetType::makeUniqueCode($name),
            'name' => $name,
            'is_active' => true,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('room-budget-types.index')
            ->with('success', 'Jenis anggaran ditambahkan.');
    }

    public function update(Request $request, RoomBudgetType $roomBudgetType): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_budget_types', 'name')->ignore($roomBudgetType->id),
            ],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ], [
            'name.required' => 'Nama anggaran wajib diisi.',
            'name.unique' => 'Nama anggaran sudah digunakan.',
            'name.max' => 'Nama anggaran maksimal 255 karakter.',
        ]);

        $roomBudgetType->update([
            'name' => trim(strip_tags($data['name'])),
            'is_active' => $data['is_active'] === '1',
        ]);

        return redirect()
            ->route('room-budget-types.index')
            ->with('success', 'Jenis anggaran diperbarui.');
    }

    public function destroy(RoomBudgetType $roomBudgetType): RedirectResponse
    {
        if ($roomBudgetType->isUsed()) {
            return redirect()
                ->route('room-budget-types.index')
                ->with('error', 'Jenis anggaran sudah digunakan pada peminjaman dan tidak dapat dihapus. Nonaktifkan saja jika tidak ingin dipilih lagi.');
        }

        $roomBudgetType->delete();

        return redirect()
            ->route('room-budget-types.index')
            ->with('success', 'Jenis anggaran dihapus.');
    }
}
