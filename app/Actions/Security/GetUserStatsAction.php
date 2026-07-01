<?php

namespace App\Actions\Security;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetUserStatsAction extends BaseAction
{
    public function handle(): array
    {
        $query = User::query()->withoutTrashed();

        $totalUsers = (clone $query)->count();

        $activeUsers = (clone $query)->where('is_active', true)->count();

        $inactiveUsers = $totalUsers - $activeUsers;

        $doctorsCount = (clone $query)
            ->whereHas('roles', fn ($q) => $q->where('roles.name', 'doctor'))
            ->count();

        $roleDistribution = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->whereNull('users.deleted_at')
            ->groupBy('roles.name')
            ->select('roles.name', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'name')
            ->toArray();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'doctors_count' => $doctorsCount,
            'role_distribution' => $roleDistribution,
        ];
    }
}
