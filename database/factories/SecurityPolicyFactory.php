<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\SecurityPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityPolicy>
 */
class SecurityPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'updated_by' => User::factory(),
            'password_min_length' => 8,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'force_two_factor' => false,
            'confirm_password_for_security_actions' => true,
            'audit_retention_days' => 90,
            'sensitive_access_retention_days' => 30,
            'session_lifetime_minutes' => 480,
            'idle_timeout_minutes' => 30,
        ];
    }
}
