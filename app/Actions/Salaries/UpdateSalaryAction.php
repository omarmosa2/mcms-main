<?php

namespace App\Actions\Salaries;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Salary;

class UpdateSalaryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $salaryId,
        array $payload,
    ): Salary {
        $salary = Salary::query()
            ->forClinic($clinicId)
            ->where('id', $salaryId)
            ->firstOrFail();

        if ($salary->status === Salary::STATUS_PAID) {
            abort(422, 'Cannot update a paid salary record.');
        }

        if (isset($payload['base_salary'])) {
            $salary->base_salary = (float) $payload['base_salary'];
        }

        if (isset($payload['allowances'])) {
            $salary->allowances = (float) $payload['allowances'];
        }

        if (isset($payload['deductions'])) {
            $salary->deductions = (float) $payload['deductions'];
        }

        $salary->net_salary = $salary->base_salary + $salary->allowances - $salary->deductions;

        if (isset($payload['notes'])) {
            $salary->notes = $payload['notes'];
        }

        $salary->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'salaries.update',
            metadata: [
                'salary_id' => $salary->id,
                'changes' => array_keys($payload),
            ],
        );

        return $salary;
    }
}
