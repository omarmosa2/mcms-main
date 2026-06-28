<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorProfile>
 */
class DoctorProfileFactory extends Factory
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
            'user_id' => null,
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement([DoctorProfile::GENDER_MALE, DoctorProfile::GENDER_FEMALE]),
            'specialty' => fake()->randomElement([
                'Family Medicine',
                'Internal Medicine',
                'Pediatrics',
                'Dermatology',
                'Cardiology',
                'Orthopedics',
            ]),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'username' => fake()->optional()->userName(),
            'employment_start_date' => fake()->optional()->dateTimeBetween('-3 years', 'now')?->format('Y-m-d'),
            'compensation_type' => fake()->randomElement([
                DoctorProfile::COMPENSATION_PERCENTAGE,
                DoctorProfile::COMPENSATION_WEEKLY_FIXED,
                DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            ]),
            'percentage_value' => function (array $attributes): ?string {
                if (($attributes['compensation_type'] ?? null) !== DoctorProfile::COMPENSATION_PERCENTAGE) {
                    return null;
                }

                $legacyValue = $attributes['compensation_value'] ?? null;

                return is_numeric($legacyValue) ? (string) $legacyValue : (string) fake()->numberBetween(10, 60);
            },
            'fixed_weekly_amount' => function (array $attributes): ?string {
                if (($attributes['compensation_type'] ?? null) !== DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
                    return null;
                }

                $legacyValue = $attributes['compensation_value'] ?? null;

                return is_numeric($legacyValue) ? (string) $legacyValue : (string) fake()->numberBetween(1000, 50000);
            },
            'fixed_monthly_amount' => function (array $attributes): ?string {
                if (($attributes['compensation_type'] ?? null) !== DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
                    return null;
                }

                $legacyValue = $attributes['compensation_value'] ?? null;

                return is_numeric($legacyValue) ? (string) $legacyValue : (string) fake()->numberBetween(1000, 50000);
            },
            'compensation_value' => fn (array $attributes): ?string => match ($attributes['compensation_type'] ?? null) {
                DoctorProfile::COMPENSATION_PERCENTAGE => (string) fake()->numberBetween(10, 60),
                DoctorProfile::COMPENSATION_WEEKLY_FIXED, DoctorProfile::COMPENSATION_MONTHLY_FIXED => (string) fake()->numberBetween(1000, 50000),
                default => null,
            },
            'currency' => 'SYP',
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
