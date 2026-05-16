<?php

namespace App\Actions\Security;

use App\Actions\BaseAction;
use App\Models\SecurityPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class UpsertSecurityPolicyAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, ?int $updatedBy, array $payload = []): SecurityPolicy
    {
        $defaults = config('security.policy_defaults');
        $fillable = [
            'password_min_length',
            'require_mixed_case',
            'require_numbers',
            'require_symbols',
            'session_lifetime_minutes',
            'idle_timeout_minutes',
            'force_two_factor',
            'confirm_password_for_security_actions',
            'audit_retention_days',
            'sensitive_access_retention_days',
        ];

        $policy = SecurityPolicy::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                ...Arr::only((array) $defaults, $fillable),
                'updated_by' => $updatedBy,
            ],
        );

        if ($payload !== []) {
            $policy->fill(Arr::only($payload, $fillable));
            $policy->updated_by = $updatedBy;
            $policy->save();
        }

        Cache::forget("clinic:{$clinicId}:security_policy");

        return $policy->fresh();
    }
}
