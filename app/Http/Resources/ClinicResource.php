<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'employees_count' => (int) ($this->employees_count ?? 0),
            'working_hours' => $this->relationLoaded('workingHours')
                ? $this->workingHours->map(fn ($wh) => [
                    'day_of_week' => $wh->day_of_week,
                    'is_active' => $wh->is_active,
                    'start_time' => $wh->start_time,
                    'end_time' => $wh->end_time,
                ])->toArray()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
