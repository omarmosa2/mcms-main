<?php

namespace App\Support;

use App\Models\SecurityPolicy;
use Illuminate\Validation\Rules\Password;

class SecurityPolicyPasswordRule
{
    public static function forClinic(?int $clinicId): Password
    {
        $defaults = (array) config('security.policy_defaults');

        $policy = $clinicId !== null
            ? SecurityPolicy::query()->forClinic($clinicId)->first()
            : null;

        $minimumLength = (int) ($policy?->password_min_length ?? $defaults['password_min_length'] ?? 12);
        $rule = Password::min(max(8, $minimumLength));

        if ((bool) ($policy?->require_mixed_case ?? $defaults['require_mixed_case'] ?? true)) {
            $rule = $rule->mixedCase();
        }

        if ((bool) ($policy?->require_numbers ?? $defaults['require_numbers'] ?? true)) {
            $rule = $rule->numbers();
        }

        if ((bool) ($policy?->require_symbols ?? $defaults['require_symbols'] ?? true)) {
            $rule = $rule->symbols();
        }

        return $rule;
    }
}
