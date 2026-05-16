<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'created',
                'updated',
                'status_changed',
                'noted',
            ]),
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
            'metadata' => [
                'source' => 'factory',
            ],
            'ip_address' => fake()->optional()->ipv4(),
            'user_agent' => fake()->optional()->userAgent(),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
