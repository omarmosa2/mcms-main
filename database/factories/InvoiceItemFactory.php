<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();

        $quantity = (float) fake()->randomElement([1, 2, 3]);
        $unitPrice = (float) fake()->randomFloat(2, 50, 400);
        $discount = (float) fake()->randomFloat(2, 0, 20);
        $tax = (float) fake()->randomFloat(2, 0, 15);
        $lineTotal = max(0, ($quantity * $unitPrice) - $discount + $tax);

        return [
            'clinic_id' => $clinic,
            'invoice_id' => Invoice::factory()->for($clinic),
            'service_code' => strtoupper(fake()->optional()->bothify('SRV-###')),
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'line_total' => $lineTotal,
        ];
    }
}
