<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends BaseModel
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_RECEPTION = 'reception';

    public const TYPE_NURSE = 'nurse';

    public const TYPE_LAB = 'lab';

    public const TYPE_CLEANER = 'cleaner';

    public const TYPE_GUARD = 'guard';

    public const TYPE_ACCOUNTANT = 'accountant';

    public const TYPE_ADMINISTRATIVE = 'administrative';

    public const TYPE_OTHER = 'other';

    public const EDUCATION_INSTITUTE = 'institute';

    public const EDUCATION_COLLEGE = 'college';

    public const EDUCATION_POSTGRADUATE = 'postgraduate';

    public const EDUCATION_NONE = 'none';

    public const EDUCATION_OTHER = 'other';

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'base_salary' => 0,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'base_salary' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryPayment::class);
    }
}
