<?php

namespace App\Http\Resources;

use App\Models\ClinicWorkingHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'doctor_profiles_count' => (int) ($this->doctor_profiles_count ?? 0),
            'working_hours' => $this->whenLoaded('clinic', fn () => $this->clinic?->workingHours
                ? $this->clinic->workingHours
                    ->sortBy(fn ($hour) => array_search($hour->day_of_week, ClinicWorkingHour::DAYS, true))
                    ->values()
                    ->map(fn ($hour) => [
                        'day_of_week' => $hour->day_of_week,
                        'is_active' => (bool) $hour->is_active,
                        'start_time' => $hour->start_time !== null ? substr((string) $hour->start_time, 0, 5) : null,
                        'end_time' => $hour->end_time !== null ? substr((string) $hour->end_time, 0, 5) : null,
                    ])
                    ->all()
                : []),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator !== null ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater !== null ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
