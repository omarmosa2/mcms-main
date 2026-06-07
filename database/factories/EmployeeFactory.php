<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'department_id' => function (array $attributes): int {
                return Department::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional()->date(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'national_id' => fake()->unique()->numerify('###########'),
            'hire_date' => fake()->dateTimeBetween('-4 years')->format('Y-m-d'),
            'status' => fake()->randomElement([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE]),
            'job_title' => fake()->randomElement(['Receptionist', 'Nurse', 'Accountant', 'Guard']),
            'employee_type' => fake()->randomElement([
                Employee::TYPE_RECEPTION,
                Employee::TYPE_NURSE,
                Employee::TYPE_LAB,
                Employee::TYPE_ACCOUNTANT,
            ]),
            'education_level' => fake()->randomElement([
                Employee::EDUCATION_INSTITUTE,
                Employee::EDUCATION_COLLEGE,
                Employee::EDUCATION_NONE,
            ]),
            'certificate_type' => fake()->optional()->randomElement(['Nursing', 'Accounting', 'Business Administration']),
            'base_salary' => fake()->randomFloat(2, 500, 3000),
            'salary_notes' => fake()->optional()->sentence(),
        ];
    }
}
