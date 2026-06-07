<?php

namespace Tests\Feature\Radiology;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\RadiologyOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiologyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_create_radiology_order_and_report(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);
        $orderResponse = $this->postJson(route('radiology.orders.store'), [
            'visit_id' => null,
            'patient_id' => $patient->id,
            'study_code' => 'CXR-PA',
            'study_name' => 'Chest X-Ray',
            'modality' => 'X-Ray',
            'notes' => 'Persistent cough',
        ]);

        $orderResponse->assertCreated();
        $orderId = (int) $orderResponse->json('data.id');

        $reportResponse = $this->postJson(route('radiology.reports.store', ['radiologyOrderId' => $orderId]), [
            'findings' => 'Mild bilateral perihilar infiltrates.',
            'impression' => 'Suggestive of early bronchopneumonia.',
        ]);

        $reportResponse->assertCreated();
        $reportResponse->assertJsonPath('data.radiology_order_id', $orderId);

        $this->assertDatabaseHas('radiology_orders', [
            'id' => $orderId,
            'status' => RadiologyOrder::STATUS_REPORTED,
        ]);

        $this->assertDatabaseHas('radiology_reports', [
            'radiology_order_id' => $orderId,
            'clinic_id' => $clinic->id,
            'impression' => 'Suggestive of early bronchopneumonia.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'radiology.reports.create',
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
