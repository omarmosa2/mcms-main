<?php

namespace App\Http\Controllers\Accounting;

use App\Actions\Accounting\CreateAccountAction;
use App\Actions\Accounting\ListAccountsAction;
use App\Actions\Accounting\UpdateAccountAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private ListAccountsAction $listAccountsAction,
        private CreateAccountAction $createAccountAction,
        private UpdateAccountAction $updateAccountAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $request->user()?->clinic_id;

        $result = $this->listAccountsAction->handle(
            clinicId: $clinicId,
            type: $request->query('type'),
            search: $request->query('search'),
            sortBy: $request->query('sort_by', 'code'),
            direction: $request->query('direction', 'asc'),
            perPage: (int) $request->query('per_page', 50),
        );

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $clinicId = $request->user()?->clinic_id;

        $account = $this->createAccountAction->handle(
            clinicId: $clinicId,
            payload: $request->all(),
        );

        return response()->json(['data' => $account], 201);
    }

    public function update(Request $request, int $accountId): JsonResponse
    {
        $account = $this->updateAccountAction->handle(
            accountId: $accountId,
            payload: $request->all(),
        );

        return response()->json(['data' => $account]);
    }
}
