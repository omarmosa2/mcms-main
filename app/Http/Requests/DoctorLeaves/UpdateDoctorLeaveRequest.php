<?php

namespace App\Http\Requests\DoctorLeaves;

use App\Models\DoctorLeave;

class UpdateDoctorLeaveRequest extends StoreDoctorLeaveRequest
{
    protected function findConflictingLeave(int $clinicId): ?string
    {
        $leaveId = (int) $this->route('doctorLeaveId');
        $doctorId = (int) $this->input('doctor_id');
        $date = (string) $this->input('leave_date');
        $type = (string) $this->input('type');

        $query = DoctorLeave::query()
            ->forClinic($clinicId)
            ->whereKeyNot($leaveId)
            ->where('doctor_id', $doctorId)
            ->whereDate('leave_date', $date)
            ->where('status', DoctorLeave::STATUS_ACTIVE);

        if ((clone $query)->where('type', DoctorLeave::TYPE_FULL_DAY)->exists()) {
            return 'Cannot add another leave while a full-day leave exists for this doctor on the same date.';
        }

        if ($type === DoctorLeave::TYPE_FULL_DAY && (clone $query)->exists()) {
            return 'Cannot add a full-day leave while another leave exists for this doctor on the same date.';
        }

        if ($type !== DoctorLeave::TYPE_HOURLY) {
            return null;
        }

        $overlaps = (clone $query)
            ->where('type', DoctorLeave::TYPE_HOURLY)
            ->where('start_time', '<', (string) $this->input('end_time'))
            ->where('end_time', '>', (string) $this->input('start_time'))
            ->exists();

        return $overlaps ? 'Cannot add overlapping hourly leaves for the same doctor and date.' : null;
    }
}
