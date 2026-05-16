<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();

        return [
            'clinic_id' => $clinic,
            'parent_id' => null,
            'code' => strtoupper(fake()->bothify('####')),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement([
                Account::TYPE_ASSET,
                Account::TYPE_LIABILITY,
                Account::TYPE_EQUITY,
                Account::TYPE_REVENUE,
                Account::TYPE_EXPENSE,
            ]),
            'opening_balance' => 0,
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Account::TYPE_ASSET]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Account::TYPE_LIABILITY]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Account::TYPE_EQUITY]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Account::TYPE_REVENUE]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Account::TYPE_EXPENSE]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Account $account) {
            if ($account->parent_id === null) {
                $account->updateQuietly(['parent_id' => null]);
            }
        });
    }
}
