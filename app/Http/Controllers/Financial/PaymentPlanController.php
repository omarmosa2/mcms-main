<?php

namespace App\Http\Controllers\Financial;

use App\Actions\Financial\ApplyPaymentPlanAction;
use App\Actions\Financial\CreatePaymentPlanAction;
use App\Actions\Financial\ListPaymentPlansAction;
use App\Http\Controllers\Controller;
use App\Models\Installment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentPlanController extends Controller
{
    public function __construct(
        private CreatePaymentPlanAction $createPaymentPlanAction,
        private ListPaymentPlansAction $listPaymentPlansAction,
        private ApplyPaymentPlanAction $applyPaymentPlanAction,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $perPage = (int) $request->get('per_page', 15);
        $isActive = $request->get('is_active');
        $search = $request->get('search');

        $plans = $this->listPaymentPlansAction->handle(
            clinicId: $clinicId,
            perPage: $perPage,
            isActive: $isActive !== null ? (bool) $isActive : null,
            search: $search,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $plans]);
        }

        return Inertia::render('financial/PaymentPlans/Index', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:48'],
            'frequency' => ['required', 'string', 'in:weekly,monthly,quarterly'],
            'min_amount' => ['required', 'integer', 'min:0'],
        ]);

        $plan = $this->createPaymentPlanAction->handle(
            clinicId: $clinicId,
            createdBy: $user->id,
            name: $validated['name'],
            installmentCount: $validated['installment_count'],
            frequency: $validated['frequency'],
            minAmount: $validated['min_amount'],
            description: $validated['description'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $plan], 201);
        }

        return redirect()->route('payment-plans.index')->with('toast', ['message' => 'Payment plan created.', 'type' => 'success']);
    }

    public function apply(Request $request, int $planId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        $installments = $this->applyPaymentPlanAction->handle(
            clinicId: $clinicId,
            paymentPlanId: $planId,
            invoiceId: $validated['invoice_id'],
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $installments], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Payment plan applied to invoice.', 'type' => 'success']);
    }

    public function installments(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $status = $request->get('status');

        $query = Installment::query()
            ->forClinic($clinicId)
            ->with(['paymentPlan', 'invoice'])
            ->orderBy('due_date');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $installments = $query->paginate((int) $request->get('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json(['data' => $installments]);
        }

        return Inertia::render('financial/Installments/Index', [
            'installments' => $installments,
        ]);
    }
}
