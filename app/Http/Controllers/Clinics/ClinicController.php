<?php

namespace App\Http\Controllers\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ClinicController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $clinic = DB::transaction(function () use ($request, $validated): Clinic {
            $clinic = Clinic::query()->create($validated);
            $request->user()?->forceFill(['clinic_id' => $clinic->id])->save();

            return $clinic;
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
            $clinic = Clinic::query()->findOrFail($clinicId);
            $clinic->fill($validated);
            $clinic->save();

            return $clinic;
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
        ]);

        return $validator->validate();
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
        ];
    }
}
