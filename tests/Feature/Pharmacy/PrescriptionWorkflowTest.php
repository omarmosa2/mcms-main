<?php

namespace Tests\Feature\Pharmacy;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_create_and_dispense_prescription_with_stock_update(): void
    {
        $clinic = Clinic::factory()->create();
        app(SyncClinicRbacAction::class)->handle($clinic->id);
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $drugResponse = $this->postJson(route('pharmacy.drugs.store'), [
            'trade_name' => 'Panadol',
            'generic_name' => 'Paracetamol',
            'unit_price' => 2.5,
            'min_stock_level' => 10,
            'current_stock' => 50,
        ]);

        $drugResponse->assertRedirect();
        $drugId = (int) PharmacyDrug::query()->where('trade_name', 'Panadol')->first()->id;

        $prescriptionResponse = $this->postJson(route('pharmacy.prescriptions.store'), [
            'visit_id' => null,
            'patient_id' => $patient->id,
            'items' => [
                [
                    'pharmacy_drug_id' => $drugId,
                    'medication_name' => 'Paracetamol 500mg',
                    'dosage' => '500mg',
                    'frequency' => 'Every 8 hours',
                    'duration' => '5 days',
                    'quantity' => 6,
                    'instructions' => 'After meals',
                ],
            ],
        ]);

        $prescriptionResponse->assertCreated();
        $prescriptionId = (int) $prescriptionResponse->json('data.id');

        $dispenseResponse = $this->postJson(
            route('pharmacy.prescriptions.dispense', ['prescriptionId' => $prescriptionId]),
            [
                'notes' => 'Dispensed from shelf A1',
                'items' => [
                    [
                        'prescription_item_id' => $prescriptionResponse->json('data.id'),
                        'quantity' => 6,
                    ],
                ],
            ],
        );

        $dispenseResponse->assertOk();

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescriptionId,
            'status' => Prescription::STATUS_DISPENSED,
        ]);

        $this->assertDatabaseHas('pharmacy_drugs', [
            'id' => $drugId,
            'current_stock' => 44,
        ]);

        $this->assertDatabaseHas('pharmacy_dispenses', [
            'prescription_id' => $prescriptionId,
            'dispensed_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'pharmacy.prescriptions.dispense',
            'auditable_id' => $prescriptionId,
        ]);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);
        $this->actingAs($user);

        return $user;
    }
}
