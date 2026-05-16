<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'patient_id' => Patient::factory()->for($clinic),
            'visit_id' => null,
            'appointment_id' => null,
            'issued_by' => null,
            'invoice_number' => strtoupper(fake()->unique()->bothify('INV-#####')),
            'status' => Invoice::STATUS_DRAFT,
            'issued_at' => null,
            'due_at' => fake()->optional()->date(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance_amount' => 0,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
