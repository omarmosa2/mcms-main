<?php

use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\DoctorScheduleService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_service_works_with_new_schema(): void
    {
        $clinic = Clinic::factory()->create();

        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'full_name' => 'Service Test Doctor',
            'specialty' => 'General',
            'compensation_type' => 'percentage',
            'is_active' => true,
        ]);

        ClinicWorkingHour::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => 1, // Monday (Carbon dayOfWeek = 1)
            'is_active' => true,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $service = app(DoctorScheduleService::class);
        $monday = now(); // today is Monday (Carbon dayOfWeek = 1)
        if ($monday->dayOfWeek !== 1) {
            $monday = $monday->next(CarbonInterface::MONDAY);
        }
        $available = $service->isDoctorAvailable(
            $clinic->id,
            $doctorUser->id,
            $monday->copy()->setTime(11, 0)->toDateTimeString(),
            30,
        );

        $this->assertTrue($available, 'Doctor should be available within schedule.');

        $outsideAvailable = $service->isDoctorAvailable(
            $clinic->id,
            $doctorUser->id,
            $monday->copy()->setTime(16, 0)->toDateTimeString(),
            30,
        );

        $this->assertFalse($outsideAvailable, 'Doctor should not be available outside schedule.');
    }
}
