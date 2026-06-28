<?php

namespace Tests\Feature\Payroll;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_page_shows_doctor_dues_for_active_doctors(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Dr. Ahmed']);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'full_name' => 'Dr. Ahmed',
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $response->assertInertia(function ($page) use ($doctorProfile) {
            $page->has('doctor_dues')
                ->has('doctor_dues', 1)
                ->where('doctor_dues.0.doctor_id', $doctorProfile->id)
                ->where('doctor_dues.0.name', 'Dr. Ahmed')
                ->where('doctor_dues.0.payment_type', 'fixed_monthly')
                ->where('doctor_dues.0.due_amount', 1500000)
                ->where('doctor_dues.0.status', 'unpaid');
        });
    }

    public function test_payroll_page_creates_monthly_dues_automatically(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('doctor_monthly_dues', [
            'doctor_id' => $doctorProfile->id,
            'salary_month' => '2026-06',
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $this->assertDatabaseHas('doctor_monthly_dues', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorProfile->id,
            'salary_month' => '2026-06',
            'payment_type' => 'fixed_monthly',
            'due_amount' => 1500000,
        ]);
    }

    public function test_payroll_page_does_not_show_inactive_doctors(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => false,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $response->assertInertia(function ($page) {
            $page->has('doctor_dues', 0);
        });
    }

    public function test_payroll_page_shows_doctor_name_from_user(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Mohammed Ali',
        ]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $response->assertInertia(function ($page) {
            $page->where('doctor_dues.0.name', 'Dr. Mohammed Ali');
        });
    }

    public function test_payroll_page_shows_doctor_name_fallback_when_no_user(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => null,
            'full_name' => 'External Doctor',
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $response->assertInertia(function ($page) {
            $page->where('doctor_dues.0.name', 'External Doctor');
        });
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
