<?php

namespace Tests\Feature\Cashbox;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Cashbox;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashboxControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_cashbox(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->postJson(route('cashbox.store'), [
            'opening_balance' => 500,
            'notes' => 'Morning shift opening',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', Cashbox::STATUS_OPEN);
        $response->assertJsonPath('data.opening_balance', 500);

        $cashboxId = (int) $response->json('data.id');

        $this->assertDatabaseHas('cashboxes', [
            'id' => $cashboxId,
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
            'opening_balance' => 500.00,
            'opened_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'cashbox.open',
            'auditable_id' => $cashboxId,
        ]);
    }

    public function test_open_cashbox_requires_permission(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('cashbox.store'), [
            'opening_balance' => 500,
        ]);

        $response->assertForbidden();
    }

    public function test_show_cashbox(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $cashbox = Cashbox::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
            'opening_balance' => 750,
        ]);

        $response = $this->getJson(route('cashbox.show', ['cashboxId' => $cashbox->id]));

        $response->assertOk();
        $response->assertJsonPath('data.id', $cashbox->id);
        $response->assertJsonPath('data.status', Cashbox::STATUS_OPEN);
        $response->assertJsonPath('data.opening_balance', 750);
    }

    public function test_update_cashbox_open_only(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $cashbox = Cashbox::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
            'opening_balance' => 200,
        ]);

        $response = $this->putJson(route('cashbox.update', ['cashboxId' => $cashbox->id]), [
            'opening_balance' => 350,
            'notes' => 'Corrected opening balance',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.opening_balance', 350);

        $this->assertDatabaseHas('cashboxes', [
            'id' => $cashbox->id,
            'opening_balance' => 350.00,
            'notes' => 'Corrected opening balance',
        ]);
    }

    public function test_close_cashbox(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $cashbox = Cashbox::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
            'opening_balance' => 100,
        ]);

        $response = $this->postJson(route('cashbox.close', ['cashboxId' => $cashbox->id]), [
            'notes' => 'End of day close',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', Cashbox::STATUS_CLOSED);

        $this->assertDatabaseHas('cashboxes', [
            'id' => $cashbox->id,
            'status' => Cashbox::STATUS_CLOSED,
            'closed_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'cashbox.close',
            'auditable_id' => $cashbox->id,
        ]);
    }

    public function test_destroy_cashbox_closed_only(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $cashbox = Cashbox::factory()->closed()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->deleteJson(route('cashbox.destroy', ['cashboxId' => $cashbox->id]));

        $response->assertOk();
        $response->assertJsonPath('message', 'Cashbox deleted successfully.');

        $this->assertSoftDeleted($cashbox);
    }

    public function test_cannot_destroy_open_cashbox(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $cashbox = Cashbox::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
        ]);

        $response = $this->deleteJson(route('cashbox.destroy', ['cashboxId' => $cashbox->id]));

        $response->assertStatus(422);

        $this->assertDatabaseHas('cashboxes', [
            'id' => $cashbox->id,
            'status' => Cashbox::STATUS_OPEN,
            'deleted_at' => null,
        ]);
    }

    public function test_bulk_destroy_closed_boxes(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $closedBox = Cashbox::factory()->closed()->create([
            'clinic_id' => $clinic->id,
        ]);

        $openBox = Cashbox::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Cashbox::STATUS_OPEN,
        ]);

        $response = $this->deleteJson(route('cashbox.bulk-destroy'), [
            'ids' => [$closedBox->id, $openBox->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('count', 1);

        $this->assertSoftDeleted($closedBox);
        $this->assertDatabaseHas('cashboxes', [
            'id' => $openBox->id,
            'status' => Cashbox::STATUS_OPEN,
            'deleted_at' => null,
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
