<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Doctors\ShowDoctorProfileAction;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorScheduleValidationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicDoctorScheduleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_without_doctor_must_be_within_clinic_hours(): void
    {
        Carbon::setTestNow('2026-06-21 08:00:00');
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $this->createClinicHours($clinic, Carbon::SUNDAY, '09:00', '17:00');

        $this->expectException(ValidationException::class);

        app(CreateAppointmentAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'scheduled_for' => '2026-06-21 18:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 0,
        ]);
    }

    public function test_doctor_can_have_multiple_non_overlapping_periods_in_clinic_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $this->createClinicHours($clinic, Carbon::SUNDAY, '09:00', '17:00');

        foreach ([['09:00', '12:00'], ['14:00', '17:00']] as [$start, $end]) {
            DoctorSchedule::query()->create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => Carbon::SUNDAY,
                'start_time' => $start,
                'end_time' => $end,
                'is_available' => true,
            ]);
        }

        $availability = app(DoctorAvailabilityService::class)->availabilityForDay(
            $clinic->id,
            $doctor->id,
            '2026-06-21',
        );

        $this->assertSame([
            ['start_time' => '09:00', 'end_time' => '12:00'],
            ['start_time' => '14:00', 'end_time' => '17:00'],
        ], $availability['available_periods']);
    }

    public function test_overlapping_doctor_schedule_is_rejected(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $this->createClinicHours($clinic, Carbon::SUNDAY, '09:00', '17:00');
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_available' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(DoctorScheduleValidationService::class)->validate(
            $clinic->id,
            $doctor->id,
            Carbon::SUNDAY,
            '11:00',
            '13:00',
        );
    }

    public function test_doctor_profile_cannot_be_loaded_across_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $profile = DoctorProfile::factory()->create(['clinic_id' => $otherClinic->id]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        $this->expectException(ModelNotFoundException::class);

        app(ShowDoctorProfileAction::class)->handle($clinic->id, $profile->id, $user->id);
    }

    private function createClinicHours(Clinic $clinic, int $day, string $start, string $end): void
    {
        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $day,
            'is_active' => true,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
