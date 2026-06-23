<?php

namespace App\Http\Controllers;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DoctorController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->resolveFilters($request);

        $doctors = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->with(['clinic', 'schedules' => fn ($query) => $query->withoutGlobalScope('clinic')])
            ->when($filters['search'] !== null, function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($filters['clinic_id'] !== null, fn (Builder $query) => $query->where('clinic_id', $filters['clinic_id']))
            ->when($filters['is_active'] !== null, fn (Builder $query) => $query->where('is_active', $filters['is_active']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $clinics = $this->resolveClinicOptions();

        $doctorsResource = DoctorResource::collection($doctors)->response()->getData(true);

        return Inertia::render('doctors/Index', [
            'doctors' => $doctorsResource,
            'clinics' => $clinics,
            'filters' => $filters,
        ]);
    }

    public function store(StoreDoctorRequest $request, AssignUserRoleAction $assignUserRoleAction): RedirectResponse
    {
        $validated = $request->validated();

        $doctor = DB::transaction(function () use ($assignUserRoleAction, $request, $validated): DoctorProfile {
            $scheduleData = collect($validated['schedules'] ?? [])
                ->filter(fn ($schedule): bool => filter_var($schedule['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->values()
                ->all();

            $account = $this->createDoctorAccount($validated, $request->user(), $assignUserRoleAction);

            $doctor = DoctorProfile::create(collect($validated)
                ->except(['schedules', 'password'])
                ->merge(['user_id' => $account?->id])
                ->toArray());

            $this->createSchedules($doctor, $scheduleData);

            return $doctor;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تمت إضافة الطبيب بنجاح.']);

        return to_route('doctors.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createDoctorAccount(array $validated, ?User $creator, AssignUserRoleAction $assignUserRoleAction): ?User
    {
        $username = trim((string) ($validated['username'] ?? ''));

        if ($username === '') {
            return null;
        }

        $account = User::query()->create([
            'clinic_id' => (int) $validated['clinic_id'],
            'name' => $validated['full_name'],
            'username' => $username,
            'email' => mb_strtolower($username).'@doctor.local',
            'password' => $validated['password'] ?? 'password',
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $assignUserRoleAction->handle($account, 'doctor', $creator?->id);

        return $account;
    }

    public function update(UpdateDoctorRequest $request, int $doctorId, AssignUserRoleAction $assignUserRoleAction): RedirectResponse
    {
        $doctor = $this->resolveDoctor($doctorId);
        $validated = $request->validated();

        DB::transaction(function () use ($assignUserRoleAction, $request, $validated, $doctor): void {
            $account = $this->syncDoctorAccount($doctor, $validated, $request->user(), $assignUserRoleAction);

            $doctor->update(collect($validated)
                ->except(['schedules', 'password', 'user_id'])
                ->merge(['user_id' => $account?->id ?? $doctor->user_id])
                ->toArray());

            if ($this->shouldUpdateSchedules($validated, $doctor)) {
                DoctorSchedule::query()
                    ->withoutGlobalScope('clinic')
                    ->where('doctor_profile_id', $doctor->id)
                    ->delete();
                $scheduleData = collect($validated['schedules'] ?? [])
                    ->filter(fn ($schedule): bool => filter_var($schedule['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->values()
                    ->all();
                $this->createSchedules($doctor, $scheduleData);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث بيانات الطبيب بنجاح.']);

        return to_route('doctors.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncDoctorAccount(DoctorProfile $doctor, array $validated, ?User $creator, AssignUserRoleAction $assignUserRoleAction): ?User
    {
        $password = $validated['password'] ?? null;

        if (! filled($password)) {
            return null;
        }

        $username = trim((string) ($validated['username'] ?? $doctor->username));
        $account = $doctor->user;

        if ($account !== null) {
            $account->update([
                'username' => $username,
                'password' => $password,
            ]);

            return $account;
        }

        $account = User::query()->create([
            'clinic_id' => (int) ($validated['clinic_id'] ?? $doctor->clinic_id),
            'name' => $validated['full_name'] ?? $doctor->full_name,
            'username' => $username,
            'email' => mb_strtolower($username).'@doctor.local',
            'password' => $password,
            'is_active' => (bool) ($validated['is_active'] ?? $doctor->is_active),
        ]);

        $assignUserRoleAction->handle($account, 'doctor', $creator?->id);

        return $account;
    }

    public function show(Request $request, int $doctorId): JsonResponse
    {
        $doctor = $this->resolveDoctor($doctorId);
        $doctor->load(['clinic', 'schedules' => fn ($query) => $query->withoutGlobalScope('clinic')]);

        return response()->json([
            'data' => DoctorResource::make($doctor),
        ]);
    }

    public function destroy(Request $request, int $doctorId): RedirectResponse
    {
        $doctor = $this->resolveDoctor($doctorId);
        $doctor->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف الطبيب وجدول دوامه. العيادة وباقي الأطباء لم يتأثروا.']);

        return to_route('doctors.index');
    }

    private function resolveDoctor(int $doctorId): DoctorProfile
    {
        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->findOrFail($doctorId);
    }

    /**
     * @param  array<int, array{day_of_week: mixed, start_time: ?string, end_time: ?string, is_available: bool}>  $scheduleData
     */
    private function createSchedules(DoctorProfile $doctor, array $scheduleData): void
    {
        $rows = [];
        $now = now();

        foreach ($scheduleData as $schedule) {
            $rows[] = [
                'doctor_profile_id' => $doctor->id,
                'clinic_id' => $doctor->clinic_id,
                'day_of_week' => WeekDay::toIndex($schedule['day_of_week']),
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'is_available' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DoctorSchedule::insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldUpdateSchedules(array $validated, DoctorProfile $doctor): bool
    {
        if (array_key_exists('schedules', $validated)) {
            return true;
        }

        return array_key_exists('clinic_id', $validated)
            && (int) $validated['clinic_id'] !== (int) $doctor->clinic_id;
    }

    /**
     * @return array{search: ?string, clinic_id: ?int, is_active: ?bool, per_page: int}
     */
    private function resolveFilters(Request $request): array
    {
        $search = trim((string) ($request->query('search') ?? ''));

        $clinicIdRaw = $request->query('clinic_id');
        $clinicId = filter_var($clinicIdRaw, FILTER_VALIDATE_INT);
        $clinicId = ($clinicId !== false && $clinicId >= 1) ? (int) $clinicId : null;

        $isActiveRaw = $request->query('is_active');
        $isActive = match ($isActiveRaw) {
            '1', 'true', true => true,
            '0', 'false', false => false,
            default => null,
        };

        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 15;
        }

        return [
            'search' => $search !== '' ? $search : null,
            'clinic_id' => $clinicId,
            'is_active' => $isActive,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: ?string, is_active: bool, working_hours: array<int, array{day_of_week: int, is_active: bool, start_time: ?string, end_time: ?string}>}>
     */
    private function resolveClinicOptions(): array
    {
        return Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->with('workingHours:id,clinic_id,day_of_week,is_active,start_time,end_time')
            ->select(['id', 'name', 'code', 'is_active'])
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (Clinic $clinic): array => [
                'id' => (int) $clinic->id,
                'name' => $clinic->name,
                'code' => $clinic->code,
                'is_active' => (bool) $clinic->is_active,
                'working_hours' => $clinic->workingHours
                    ->map(fn ($workingHour): array => [
                        'day_of_week' => WeekDay::toIndex($workingHour->getRawOriginal('day_of_week')),
                        'is_active' => (bool) $workingHour->is_active,
                        'start_time' => $this->formatTime($workingHour->start_time),
                        'end_time' => $this->formatTime($workingHour->end_time),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 5);
    }
}
