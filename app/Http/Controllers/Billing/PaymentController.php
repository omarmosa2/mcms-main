<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RecordPaymentAction;
use App\Actions\Billing\RefundPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\RecordPaymentRequest;
use App\Http\Requests\Billing\RefundPaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private RecordPaymentAction $recordPaymentAction,
        private RefundPaymentAction $refundPaymentAction,
    ) {}

    public function store(RecordPaymentRequest $request, int $invoiceId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $payment = $this->recordPaymentAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return PaymentResource::make($payment)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded successfully.']);

        return to_route('billing.invoices.index');
    }

    public function refund(RefundPaymentRequest $request, int $paymentId): PaymentResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $payment = $this->refundPaymentAction->handle(
            clinicId: $clinicId,
            paymentId: $paymentId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return PaymentResource::make($payment);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment refunded successfully.']);

        return to_route('billing.invoices.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
