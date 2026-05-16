<?php

namespace Tests\Feature\Lab;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_create_lab_order_and_record_result(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);
        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
        ]);

        $orderResponse = $this->postJson(route('lab.orders.store'), [
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'test_code' => 'CBC',
            'test_name' => 'Complete Blood Count',
            'notes' => 'Urgent sample',
        ]);

        $orderResponse->assertCreated();
        $orderId = (int) $orderResponse->json('data.id');

        $resultResponse = $this->postJson(route('lab.results.store', ['labOrderId' => $orderId]), [
            'result_value' => 'WBC 7.2, Hb 13.6',
            'unit' => 'x10^9/L, g/dL',
            'reference_range' => 'Normal',
            'notes' => 'Within normal limits',
        ]);

        $resultResponse->assertCreated();
        $resultResponse->assertJsonPath('data.lab_order_id', $orderId);

        $this->assertDatabaseHas('lab_orders', [
            'id' => $orderId,
            'status' => LabOrder::STATUS_RESULTED,
        ]);

        $this->assertDatabaseHas('lab_results', [
            'lab_order_id' => $orderId,
            'clinic_id' => $clinic->id,
            'result_value' => 'WBC 7.2, Hb 13.6',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'lab.results.create',
            'auditable_id' => $orderId,
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
