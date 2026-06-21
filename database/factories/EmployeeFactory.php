<?php

namespace Database\Factories;

use App\Models\Clinic;
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
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional()->date(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'national_id' => fake()->unique()->numerify('###########'),
            'marital_status' => fake()->optional()->randomElement([
                Employee::MARITAL_SINGLE,
                Employee::MARITAL_MARRIED,
                Employee::MARITAL_DIVORCED,
                Employee::MARITAL_WIDOWED,
            ]),
            'hire_date' => fake()->dateTimeBetween('-4 years')->format('Y-m-d'),
            'status' => fake()->randomElement([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE]),
            'job_title' => fake()->randomElement(['Receptionist', 'Nurse', 'Accountant', 'Guard', 'Lab Technician']),
            'employee_type' => fake()->randomElement([
                Employee::TYPE_RECEPTION,
                Employee::TYPE_NURSE,
                Employee::TYPE_LAB,
                Employee::TYPE_USER,
                Employee::TYPE_ACCOUNTANT,
                Employee::TYPE_ADMINISTRATIVE,
            ]),
            'specialty' => fake()->optional()->randomElement(['General', 'Pediatrics', 'Emergency', 'Radiology']),
            'job_description' => fake()->optional()->sentence(10),
            'education_level' => fake()->randomElement([
                Employee::EDUCATION_NONE,
                Employee::EDUCATION_SECONDARY,
                Employee::EDUCATION_INSTITUTE,
                Employee::EDUCATION_COLLEGE,
                Employee::EDUCATION_POSTGRADUATE,
            ]),
            'certificate_name' => fake()->optional()->randomElement(['Nursing Diploma', 'Bachelor of Accounting', 'Business Administration', 'Medical Lab Technology']),
            'education_specialty' => fake()->optional()->randomElement(['Nursing', 'Accounting', 'Business', 'Computer Science']),
            'graduation_year' => fake()->optional()->numberBetween(1990, 2025),
            'issuing_institution' => fake()->optional()->randomElement(['Damascus University', 'Aleppo University', 'Tishreen University', 'Institute of Nursing']),
            'base_salary' => fake()->randomFloat(2, 500, 3000),
            'additional_allowance' => fake()->optional()->randomFloat(2, 0, 500),
            'salary_notes' => fake()->optional()->sentence(),
        ];
    }
}
