<?php

namespace Tests\Feature\Doctors;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Exports\DoctorExport;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DoctorExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_export_downloads_filtered_doctors_with_schedules(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $clinic = Clinic::factory()->create([
            'name' => 'Export Clinic',
        ]);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Export Filter',
            'specialty' => 'Cardiology',
            'phone' => '0999999999',
            'username' => 'exportdoctor',
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => '40',
            'percentage_value' => '40',
            'currency' => 'SYP',
            'is_active' => true,
        ]);

        DoctorSchedule::query()->create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '14:30',
            'is_available' => true,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Hidden Export',
            'is_active' => false,
        ]);

        $this->authenticateForClinic($clinic);

        $this->get(route('doctors.export', [
            'search' => 'Export Filter',
            'clinic_id' => $clinic->id,
            'is_active' => '1',
        ]))->assertOk();

        Excel::assertDownloaded(
            '/doctors_export_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx/',
            function (DoctorExport $export) use ($doctor): bool {
                $exportedDoctors = $export->query()->get();
                $rows = $export->map($exportedDoctors->first());

                return $exportedDoctors->pluck('id')->all() === [$doctor->id]
                    && $rows[0][0] === 'Dr. Export Filter'
                    && $rows[0][1] === 'Export Clinic'
                    && $rows[0][2] === 'Cardiology'
                    && $rows[0][13] === 'دوام'
                    && $rows[0][14] === '10:00'
                    && $rows[0][15] === '14:30';
            },
        );
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
