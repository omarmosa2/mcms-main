<?php

namespace App\Http\Controllers\Cashbox;

use App\Actions\Cashbox\CloseCashboxAction;
use App\Actions\Cashbox\DestroyCashboxAction;
use App\Actions\Cashbox\GetDailyExpensesAction;
use App\Actions\Cashbox\GetDailyIncomeAction;
use App\Actions\Cashbox\OpenCashboxAction;
use App\Actions\Cashbox\UpdateCashboxAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashboxResource;
use App\Models\Cashbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CashboxController extends Controller
{
    public function __construct(
        private OpenCashboxAction $openCashboxAction,
        private CloseCashboxAction $closeCashboxAction,
        private UpdateCashboxAction $updateCashboxAction,
        private DestroyCashboxAction $destroyCashboxAction,
        private GetDailyIncomeAction $getDailyIncomeAction,
        private GetDailyExpensesAction $getDailyExpensesAction,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $today = now()->toDateString();

        $todayBox = Cashbox::query()
            ->forClinic($clinicId)
            ->where('box_date', $today)
            ->with(['opener:id,name'])
            ->first();

        $dailyIncome = $this->getDailyIncomeAction->handle($clinicId, $today);
        $dailyExpenses = $this->getDailyExpensesAction->handle($clinicId, $today);

        $currentBalance = $todayBox
            ? $todayBox->opening_balance + $dailyIncome - $dailyExpenses
            : 0;

        $recentBoxes = Cashbox::query()
            ->forClinic($clinicId)
            ->orderBy('box_date', 'desc')
            ->with(['opener:id,name', 'closer:id,name'])
            ->paginate($request->integer('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json([
                'today_box' => $todayBox ? CashboxResource::make($todayBox) : null,
                'daily_income' => $dailyIncome,
                'daily_expenses' => $dailyExpenses,
                'current_balance' => $currentBalance,
            ]);
        }

        return Inertia::render('cashbox/Index', [
            'today_box' => $todayBox ? CashboxResource::make($todayBox)->response()->getData(true) : null,
            'daily_income' => $dailyIncome,
            'daily_expenses' => $dailyExpenses,
            'current_balance' => $currentBalance,
            'recent_boxes' => CashboxResource::collection($recentBoxes)->response()->getData(true),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashbox = $this->openCashboxAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return CashboxResource::make($cashbox)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم فتح الصندوق بنجاح.']);

        return to_route('cashbox.index');
    }

    public function show(Request $request, int $cashboxId): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $cashbox = Cashbox::query()
            ->forClinic($clinicId)
            ->with(['opener:id,name', 'closer:id,name'])
            ->findOrFail($cashboxId);

        if ($request->expectsJson()) {
            return response()->json(['data' => CashboxResource::make($cashbox)]);
        }

        return Inertia::render('cashbox/Show', [
            'cashbox' => CashboxResource::make($cashbox)->response()->getData(true),
        ]);
    }

    public function update(Request $request, int $cashboxId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashbox = $this->updateCashboxAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            cashboxId: $cashboxId,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return CashboxResource::make($cashbox)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث الصندوق بنجاح.']);

        return to_route('cashbox.index');
    }

    public function close(Request $request, int $cashboxId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashbox = $this->closeCashboxAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            cashboxId: $cashboxId,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return CashboxResource::make($cashbox)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إغلاق الصندوق بنجاح.']);

        return to_route('cashbox.index');
    }

    public function destroy(Request $request, int $cashboxId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->destroyCashboxAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            cashboxId: $cashboxId,
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cashbox deleted successfully.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف الصندوق بنجاح.']);

        return to_route('cashbox.index');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        $count = $this->destroyCashboxAction->bulkDestroy(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            ids: $validated['ids'],
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => "$count cashboxes deleted.", 'count' => $count]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "تم حذف $count صندوق بنجاح."]);

        return to_route('cashbox.index');
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
