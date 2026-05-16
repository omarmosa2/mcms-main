<?php

namespace App\Actions\Salaries;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Salary;

class DeleteSalaryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $salaryId,
    ): void {
        $salary = Salary::query()
            ->forClinic($clinicId)
            ->where('id', $salaryId)
            ->firstOrFail();

        if ($salary->status === Salary::STATUS_PAID) {
            abort(422, 'Cannot delete a paid salary record.');
        }

        $deletedId = $salary->id;
        $userId = $salary->user_id;
        $periodMonth = $salary->period_month;

        $salary->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'salaries.delete',
            metadata: [
                'deleted_salary_id' => $deletedId,
                'user_id' => $userId,
                'period_month' => $periodMonth,
            ],
        );
    }
}
