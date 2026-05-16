<?php

namespace App\Actions\Salaries;

use App\Actions\Accounting\AutoPostAction;
use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Salary;

class ProcessSalaryPaymentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AutoPostAction $autoPostAction,
    ) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $salaryId,
        bool $approve = true,
    ): Salary {
        $salary = Salary::query()
            ->forClinic($clinicId)
            ->where('id', $salaryId)
            ->firstOrFail();

        if ($approve) {
            $salary->update([
                'status' => Salary::STATUS_PAID,
                'paid_at' => now(),
                'paid_by' => $userId,
            ]);

            $this->autoPostAction->handleSalaryPaid($salary);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'salaries.payment',
                metadata: [
                    'salary_id' => $salary->id,
                    'user_id' => $salary->user_id,
                    'period_month' => $salary->period_month,
                    'amount' => $salary->net_salary,
                ],
            );
        } else {
            $salary->update([
                'status' => Salary::STATUS_APPROVED,
            ]);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'salaries.approve',
                metadata: [
                    'salary_id' => $salary->id,
                    'user_id' => $salary->user_id,
                    'period_month' => $salary->period_month,
                ],
            );
        }

        return $salary;
    }
}
