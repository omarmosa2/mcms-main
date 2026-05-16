<?php

namespace App\Actions\Salaries;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateSalaryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        array $payload,
    ): Salary {
        $userId = $payload['user_id'];
        $periodMonth = $payload['period_month'];

        $existingSalary = Salary::query()
            ->forClinic($clinicId)
            ->where('user_id', $userId)
            ->where('period_month', $periodMonth)
            ->exists();

        if ($existingSalary) {
            throw ValidationException::withMessages([
                'period_month' => 'A salary record already exists for this user in this period.',
            ]);
        }

        $user = User::query()
            ->forClinic($clinicId)
            ->where('id', $userId)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found.',
            ]);
        }

        $baseSalary = (float) $payload['base_salary'];
        $allowances = (float) ($payload['allowances'] ?? 0);
        $deductions = (float) ($payload['deductions'] ?? 0);
        $netSalary = $baseSalary + $allowances - $deductions;

        $salary = Salary::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'base_salary' => $baseSalary,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'status' => Salary::STATUS_DRAFT,
            'period_month' => $periodMonth,
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'salaries.create',
            metadata: [
                'salary_id' => $salary->id,
                'user_id' => $userId,
                'period_month' => $periodMonth,
                'net_salary' => $netSalary,
            ],
        );

        return $salary;
    }
}
