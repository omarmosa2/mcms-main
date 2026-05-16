<?php

namespace App\Http\Controllers\Financial;

use App\Actions\Financial\ProcessInstallmentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function __construct(
        private ProcessInstallmentAction $processInstallmentAction,
    ) {}

    public function pay(Request $request, int $installmentId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $installment = $this->processInstallmentAction->handle(
            clinicId: $clinicId,
            userId: $user->id,
            installmentId: $installmentId,
            amount: $validated['amount'],
            notes: $validated['notes'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $installment]);
        }

        return redirect()->back()->with('toast', ['message' => 'Installment payment recorded.', 'type' => 'success']);
    }
}
