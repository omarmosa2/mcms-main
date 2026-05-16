<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\BaseAction;
use App\Models\DoctorSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDoctorSchedulesAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<DoctorSchedule>
     */
    public function handle(int $clinicId, array $filters = []): LengthAwarePaginator
    {
        $query = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with('doctor');

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['is_available'])) {
            $query->where('is_available', (bool) $filters['is_available']);
        }

        $query->orderBy('doctor_id')->orderBy('day_of_week');

        return $query->paginate($filters['per_page'] ?? 20);
    }
}
