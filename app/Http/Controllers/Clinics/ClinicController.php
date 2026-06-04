<?php

namespace App\Http\Controllers\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Services\ClinicWorkingHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

class ClinicController extends Controller
{
    public function __construct(private ClinicWorkingHoursService $clinicWorkingHoursService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $clinic = DB::transaction(function () use ($request, $validated): Clinic {
            $workingHours = $validated['working_hours'] ?? [];
            unset($validated['working_hours']);

            $clinic = Clinic::query()->create($validated);
            $request->user()?->forceFill(['clinic_id' => $clinic->id])->save();
            $this->clinicWorkingHoursService->replaceForClinic($clinic->id, $workingHours);

            return $clinic->load('workingHours');
        });

        return response()->json([
            'data' => $this->clinicPayload($clinic),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $clinicId): JsonResponse
    {
        $userClinicId = $request->user()?->clinic_id;

        if ($userClinicId === null || (int) $userClinicId !== $clinicId) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        $validated = $this->validatedPayload($request, $clinicId, partial: true);

        $clinic = DB::transaction(function () use ($clinicId, $validated): Clinic {
            $workingHours = $validated['working_hours'] ?? null;
            unset($validated['working_hours']);

            $clinic = Clinic::query()->findOrFail($clinicId);
            $clinic->fill($validated);
            $clinic->save();

            if (is_array($workingHours)) {
                $this->clinicWorkingHoursService->replaceForClinic($clinic->id, $workingHours);
            }

            return $clinic->load('workingHours');
        });

        return response()->json([
            'data' => $this->clinicPayload($clinic),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?int $clinicId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $validator = ValidatorFacade::make($request->all(), [
            'code' => [
                $required,
                'string',
                'max:50',
                Rule::unique('clinics', 'code')->ignore($clinicId),
            ],
            'name' => [$required, 'string', 'max:120'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:3'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'working_hours' => ['sometimes', 'array'],
            'working_hours.*.day_of_week' => ['required_with:working_hours', 'string', Rule::in(ClinicWorkingHour::DAYS)],
            'working_hours.*.is_active' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $validator->after(fn (Validator $validator): mixed => $this->validateWorkingHours($validator, $request));

        return $validator->validate();
    }

    private function validateWorkingHours(Validator $validator, Request $request): void
    {
        foreach ((array) $request->input('working_hours', []) as $index => $row) {
            $isActive = filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = $row['start_time'] ?? null;
            $endTime = $row['end_time'] ?? null;

            if ($isActive) {
                if ($startTime === null || $startTime === '') {
                    $validator->errors()->add("working_hours.$index.start_time", 'وقت بداية الدوام مطلوب.');
                }

                if ($endTime === null || $endTime === '') {
                    $validator->errors()->add("working_hours.$index.end_time", 'وقت نهاية الدوام مطلوب.');
                }

                if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
                    $validator->errors()->add("working_hours.$index.end_time", 'وقت نهاية الدوام يجب أن يكون بعد وقت البداية.');
                }

                continue;
            }

            if ($startTime !== null || $endTime !== null) {
                $validator->errors()->add("working_hours.$index.start_time", 'الأيام غير المفعلة لا تقبل أوقات دوام.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function clinicPayload(Clinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'code' => $clinic->code,
            'name' => $clinic->name,
            'legal_name' => $clinic->legal_name,
            'timezone' => $clinic->timezone,
            'currency' => $clinic->currency,
            'phone' => $clinic->phone,
            'email' => $clinic->email,
            'address' => $clinic->address,
            'is_active' => (bool) $clinic->is_active,
            'working_hours' => $this->clinicWorkingHoursService->getForClinic((int) $clinic->id),
        ];
    }
}
