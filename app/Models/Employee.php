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

    public const TYPE_USER = 'user';

    public const TYPE_CLEANER = 'cleaner';

    public const TYPE_GUARD = 'guard';

    public const TYPE_ACCOUNTANT = 'accountant';

    public const TYPE_ADMINISTRATIVE = 'administrative';

    public const TYPE_OTHER = 'other';

    public const EDUCATION_SECONDARY = 'secondary';

    public const EDUCATION_INSTITUTE = 'institute';

    public const EDUCATION_COLLEGE = 'college';

    public const EDUCATION_POSTGRADUATE = 'postgraduate';

    public const EDUCATION_NONE = 'none';

    public const EDUCATION_OTHER = 'other';

    public const MARITAL_SINGLE = 'single';

    public const MARITAL_MARRIED = 'married';

    public const MARITAL_DIVORCED = 'divorced';

    public const MARITAL_WIDOWED = 'widowed';

    protected $fillable = [
        'clinic_id',
        'department_id',
        'user_id',
        'full_name',
        'gender',
        'birth_date',
        'phone',
        'address',
        'national_id',
        'marital_status',
        'hire_date',
        'status',
        'job_title',
        'employee_type',
        'specialty',
        'job_description',
        'education_level',
        'certificate_name',
        'education_specialty',
        'graduation_year',
        'issuing_institution',
        'base_salary',
        'additional_allowance',
        'salary_notes',
        'sham_cash_qr_path',
    ];

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
            'additional_allowance' => 'decimal:2',
            'graduation_year' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryPayment::class);
    }

    public function monthlySalaries(): HasMany
    {
        return $this->hasMany(EmployeeMonthlySalary::class);
    }
}
