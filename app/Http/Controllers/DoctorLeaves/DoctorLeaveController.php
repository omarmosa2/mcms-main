<?php

namespace App\Http\Controllers\DoctorLeaves;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorLeaves\StoreDoctorLeaveRequest;
use App\Http\Requests\DoctorLeaves\UpdateDoctorLeaveRequest;
use App\Http\Resources\DoctorLeaveResource;
use App\Models\Clinic;
use App\Models\DoctorLeave;
use App\Models\DoctorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DoctorLeaveController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $filters = $this->resolveIndexFilters($request);

        $query = DoctorLeave::query()
            ->withoutGlobalScope('clinic')
            ->with(['doctor', 'clinic']);

        if ($filters['clinic_id'] !== null) {
            $query->where('clinic_id', $filters['clinic_id']);
        }

        if ($filters['doctor_id'] !== null) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate('leave_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->whereDate('leave_date', '<=', $filters['date_to']);
        }

        $leaves = $query
            ->orderByDesc('leave_date')
            ->orderBy('start_time')
            ->paginate($filters['per_page']);

        $leavesResource = DoctorLeaveResource::collection($leaves);

        if ($request->expectsJson()) {
            return $leavesResource;
        }

        return Inertia::render('doctor-leaves/Index', [
            'leaves' => $leavesResource->response()->getData(true),
            'doctors' => $this->doctorsForSelect($filters['clinic_id'] ?? 0),
            'clinics' => $this->clinicsForSelect(),
            'filters' => $filters,
            'type_options' => [DoctorLeave::TYPE_FULL_DAY, DoctorLeave::TYPE_HOURLY],
            'status_options' => [DoctorLeave::STATUS_ACTIVE, DoctorLeave::STATUS_CANCELED],
        ]);
    }

    public function store(StoreDoctorLeaveRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $clinicId = (int) ($validated['clinic_id'] ?? $this->resolveClinicId($request));

        $leave = DoctorLeave::query()->create([
            ...$this->normalizedPayload($validated),
            'clinic_id' => $clinicId,
            'status' => DoctorLeave::STATUS_ACTIVE,
        ]);

        if ($request->expectsJson()) {
            return DoctorLeaveResource::make($leave->load(['doctor', 'clinic']))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تمت إضافة إجازة الطبيب بنجاح.']);

        return to_route('doctor-leaves.index');
    }

    public function update(UpdateDoctorLeaveRequest $request, int $doctorLeaveId): DoctorLeaveResource|RedirectResponse
    {
        $validated = $request->validated();
        $clinicId = (int) ($validated['clinic_id'] ?? $this->resolveClinicId($request));

        $leave = DoctorLeave::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->findOrFail($doctorLeaveId);

        $leave->fill([
            ...$this->normalizedPayload($validated),
            'status' => DoctorLeave::STATUS_ACTIVE,
        ]);
        $leave->save();

        if ($request->expectsJson()) {
            return DoctorLeaveResource::make($leave->load(['doctor', 'clinic']));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تعديل إجازة الطبيب بنجاح.']);

        return to_route('doctor-leaves.index');
    }

    public function cancel(Request $request, int $doctorLeaveId): DoctorLeaveResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $leave = DoctorLeave::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->findOrFail($doctorLeaveId);

        $leave->forceFill(['status' => DoctorLeave::STATUS_CANCELED])->save();

        if ($request->expectsJson()) {
            return DoctorLeaveResource::make($leave->load(['doctor', 'clinic']));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إلغاء إجازة الطبيب بنجاح.']);

        return to_route('doctor-leaves.index');
    }

    public function destroy(Request $request, int $doctorLeaveId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $leave = DoctorLeave::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->findOrFail($doctorLeaveId);

        $leave->delete();

        if ($request->expectsJson()) {
            return DoctorLeaveResource::make($leave)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف إجازة الطبيب بنجاح.']);

        return to_route('doctor-leaves.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array<int, array{id: int, name: string|null, clinic_id: int|null, clinic: array{id: int, name: string}|null}>
     */
    private function doctorsForSelect(int $clinicId = 0): array
    {
        $query = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true)
            ->with(['user:id,clinic_id,name', 'clinic:id,name']);

        if ($clinicId > 0) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->get()
            ->map(fn (DoctorProfile $profile): array => [
                'id' => (int) $profile->user_id,
                'name' => $profile->user?->name,
                'clinic_id' => $profile->clinic_id,
                'clinic' => $profile->clinic === null ? null : [
                    'id' => $profile->clinic->id,
                    'name' => $profile->clinic->name,
                ],
            ])
            ->filter(fn (array $doctor): bool => $doctor['name'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function clinicsForSelect(): array
    {
        return Clinic::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->name,
            ])
            ->all();
    }

    /**
     * @return array{
     *     doctor_id: ?int,
     *     clinic_id: ?int,
     *     status: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     per_page: int
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        return [
            'doctor_id' => $this->normalizeNullableInteger($request->query('doctor_id')),
            'clinic_id' => $this->normalizeNullableInteger($request->query('clinic_id')),
            'status' => $this->normalizeStatus($request->query('status')),
            'date_from' => $this->normalizeNullableDate($request->query('date_from')),
            'date_to' => $this->normalizeNullableDate($request->query('date_to')),
            'per_page' => $this->normalizePerPage($request->query('per_page', 15)),
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = trim((string) ($value ?? ''));

        return in_array($status, [DoctorLeave::STATUS_ACTIVE, DoctorLeave::STATUS_CANCELED], true) ? $status : null;
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload): array
    {
        if (($payload['type'] ?? null) === DoctorLeave::TYPE_FULL_DAY) {
            $payload['start_time'] = null;
            $payload['end_time'] = null;
        }

        return $payload;
    }
}
