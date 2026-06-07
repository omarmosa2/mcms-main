<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EmployeeMonthlySalaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeMonthlySalary extends BaseModel
{
    /** @use HasFactory<EmployeeMonthlySalaryFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryPayment::class);
    }
}
