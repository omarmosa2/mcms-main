<?php

namespace App\Actions\Reports;

use App\Actions\BaseAction;
use App\Models\Account;
use App\Models\DoctorDuePayment;
use App\Models\DoctorSalaryPayment;
use App\Models\EmployeeSalaryPayment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Salary;
use Carbon\CarbonImmutable;

class GetFinancialStatementsReportAction extends BaseAction
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     income_statement: array<string, float>,
     *     balance_sheet: array<string, float>,
     *     cash_flow: array<string, float>
     * }
     */
    public function handle(int $clinicId, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $to] = $this->resolvePeriod($fromDate, $toDate);

        $revenue = (float) Invoice::query()
            ->forClinic($clinicId)
            ->whereBetween('issued_at', [$from, $to])
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_PAID,
            ])
            ->sum('total_amount');

        $operatingExpenses = (float) Expense::query()
            ->forClinic($clinicId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', Expense::STATUS_APPROVED)
            ->sum('amount');

        $legacyPayrollExpenses = (float) Salary::query()
            ->forClinic($clinicId)
            ->whereBetween('paid_at', [$from, $to])
            ->where('status', Salary::STATUS_PAID)
            ->sum('net_salary');
        $employeePayrollExpenses = (float) EmployeeSalaryPayment::query()
            ->forClinic($clinicId)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
        $doctorPayrollExpenses = (float) DoctorSalaryPayment::query()
            ->forClinic($clinicId)
            ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_paid');
        $doctorDuePayrollExpenses = (float) DoctorDuePayment::query()
            ->forClinic($clinicId)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
        $payrollExpenses = $legacyPayrollExpenses + $employeePayrollExpenses + $doctorPayrollExpenses + $doctorDuePayrollExpenses;

        $totalExpenses = round($operatingExpenses + $payrollExpenses, 2);
        $netIncome = round($revenue - $totalExpenses, 2);

        $assets = (float) Account::query()
            ->forClinic($clinicId)
            ->where('type', Account::TYPE_ASSET)
            ->get()
            ->sum('balance');

        $liabilities = (float) Account::query()
            ->forClinic($clinicId)
            ->where('type', Account::TYPE_LIABILITY)
            ->get()
            ->sum('balance');

        $equity = (float) Account::query()
            ->forClinic($clinicId)
            ->where('type', Account::TYPE_EQUITY)
            ->get()
            ->sum('balance');

        $cashInflow = (float) Payment::query()
            ->forClinic($clinicId)
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('status', [Payment::STATUS_RECORDED, Payment::STATUS_REFUNDED])
            ->sum('amount');

        $cashOutflow = (float) (
            Expense::query()
                ->forClinic($clinicId)
                ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
                ->where('status', Expense::STATUS_APPROVED)
                ->sum('amount')
            + Salary::query()
                ->forClinic($clinicId)
                ->whereBetween('paid_at', [$from, $to])
                ->where('status', Salary::STATUS_PAID)
                ->sum('net_salary')
            + EmployeeSalaryPayment::query()
                ->forClinic($clinicId)
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount')
            + DoctorSalaryPayment::query()
                ->forClinic($clinicId)
                ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
                ->sum('amount_paid')
            + DoctorDuePayment::query()
                ->forClinic($clinicId)
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount')
        );

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'income_statement' => [
                'revenue' => round($revenue, 2),
                'operating_expenses' => round($operatingExpenses, 2),
                'payroll_expenses' => round($payrollExpenses, 2),
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
            ],
            'balance_sheet' => [
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'equity' => round($equity + $netIncome, 2),
                'liabilities_and_equity' => round($liabilities + $equity + $netIncome, 2),
            ],
            'cash_flow' => [
                'cash_inflow' => round($cashInflow, 2),
                'cash_outflow' => round($cashOutflow, 2),
                'net_cashflow' => round($cashInflow - $cashOutflow, 2),
            ],
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolvePeriod(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate !== null
            ? CarbonImmutable::parse($fromDate)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $to = $toDate !== null
            ? CarbonImmutable::parse($toDate)->endOfDay()
            : CarbonImmutable::now()->endOfMonth();

        if ($from->greaterThan($to)) {
            return [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }
}
