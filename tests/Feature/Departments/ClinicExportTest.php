<?php

namespace Tests\Feature\Departments;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Exports\ClinicExport;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ClinicExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_export_downloads_filtered_clinics_with_working_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));
        Excel::fake();
        Excel::matchByRegex();

        $clinic = Clinic::factory()->create([
            'name' => 'Dental Export Clinic',
            'code' => 'DENT',
            'description' => 'Dental working hours',
            'is_active' => true,
        ]);

        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => 1,
            'is_active' => true,
            'start_time' => '09:00',
            'end_time' => '15:00',
        ]);

        Clinic::factory()->create([
            'name' => 'Hidden Export Clinic',
            'is_active' => false,
        ]);

        $this->authenticateForClinic($clinic);

        $this->get(route('clinics.export', [
            'search' => 'Dental Export',
            'is_active' => '1',
            'sort_by' => 'name',
            'sort_direction' => 'asc',
        ]))->assertOk();

        Excel::assertDownloaded(
            '/clinics_export_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx/',
            function (ClinicExport $export) use ($clinic): bool {
                $exportedClinics = $export->query()->get();
                $rows = $export->map($exportedClinics->first());

                return $exportedClinics->pluck('id')->all() === [$clinic->id]
                    && $rows[0][0] === 'Dental Export Clinic'
                    && $rows[0][1] === 'DENT'
                    && $rows[0][5] === 'دوام'
                    && $rows[0][6] === '09:00'
                    && $rows[0][7] === '15:00';
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
