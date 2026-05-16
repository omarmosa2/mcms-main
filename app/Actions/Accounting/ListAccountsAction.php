<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;

class ListAccountsAction extends BaseAction
{
    public function handle(
        int $clinicId,
        ?string $type = null,
        ?string $search = null,
        ?string $sortBy = 'code',
        string $direction = 'asc',
        int $perPage = 50,
    ): array {
        $query = Account::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with('parent:id,code,name');

        if ($type) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $validSortColumns = ['code', 'name', 'type', 'created_at'];
        if (in_array($sortBy, $validSortColumns, true)) {
            $query->orderBy($sortBy, $direction);
        }

        $accounts = $query->paginate($perPage);

        return [
            'data' => $accounts->items(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ];
    }
}
