<?php

namespace Tests\Feature\Reports;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_audit_json_is_scoped_to_authenticated_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        AuditLog::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.show',
            'occurred_at' => now(),
        ]);

        AuditLog::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => null,
            'action' => 'patients.show',
            'occurred_at' => now(),
        ]);

        $response = $this->getJson(route('reports.audit'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'patients.show');
    }

    public function test_reports_audit_export_returns_csv(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        AuditLog::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.update',
            'occurred_at' => now(),
        ]);

        $response = $this->get(route('reports.audit.export'));

        $response->assertOk();

        $contentType = (string) $response->headers->get('content-type');
        $this->assertStringContainsString('text/csv', $contentType);

        $content = $response->streamedContent();
        $this->assertStringContainsString('event_id,actor,action,resource_type,resource_id,reason,occurred_at', $content);
        $this->assertStringContainsString('patients.update', $content);
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
