<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();
        $amount = (float) fake()->randomFloat(2, 20, 500);

        return [
            'clinic_id' => $clinic,
            'invoice_id' => Invoice::factory()->for($clinic),
            'received_by' => null,
            'payment_reference' => strtoupper(fake()->optional()->bothify('PAY-####')),
            'method' => fake()->randomElement(['cash', 'card', 'bank_transfer', 'insurance', 'online']),
            'status' => Payment::STATUS_RECORDED,
            'amount' => $amount,
            'refund_amount' => 0,
            'paid_at' => now(),
            'refunded_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
