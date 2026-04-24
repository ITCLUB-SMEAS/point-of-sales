<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminCashMovementRequest;
use App\Services\CashMovementService;
use Illuminate\Http\RedirectResponse;

class AdminCashManagementController extends Controller
{
    public function store(
        StoreAdminCashMovementRequest $request,
        CashMovementService $cashMovementService
    ): RedirectResponse {
        $validated = $request->validated();

        $cashMovementService->record($request->user(), [
            'cashier_shift_id' => (int) $validated['cashier_shift_id'],
            'type' => $validated['type'],
            'amount' => (int) $validated['amount'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'],
        ]);

        return redirect('/admin/cash-management')->with('status', 'Kas berhasil dicatat.');
    }
}
