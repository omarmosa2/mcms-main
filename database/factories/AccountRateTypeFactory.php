<?php

namespace Database\Factories;

use App\Models\AccountRateType;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRateType>
 */
class AccountRateTypeFactory extends Factory
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
            'name' => fake()->randomElement(['VAT', 'Service Tax', 'Discount', 'Additional Discount']),
            'code' => strtoupper(fake()->unique()->bothify('RATE###')),
            'rate_percentage' => fake()->randomFloat(2, 5, 20),
            'type' => fake()->randomElement([
                AccountRateType::TYPE_TAX,
                AccountRateType::TYPE_DISCOUNT,
                AccountRateType::TYPE_OTHER,
            ]),
            'is_active' => true,
        ];
    }

    public function tax(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountRateType::TYPE_TAX,
            'name' => 'VAT',
            'rate_percentage' => 15,
        ]);
    }

    public function discount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountRateType::TYPE_DISCOUNT,
            'name' => 'Discount',
            'rate_percentage' => 10,
        ]);
    }
}
