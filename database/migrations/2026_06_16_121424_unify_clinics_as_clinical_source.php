<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->copyClinicalDepartmentsToClinics();
        $this->moveClinicalReferencesToClinics();
        $this->dropClinicalDepartmentReferences();
    }

    public function down(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('doctor_profiles', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('user_id')->constrained('departments')->nullOnDelete();
                $table->index(['clinic_id', 'department_id']);
            }
        });

        Schema::table('patient_card_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('patient_card_visits', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('doctor_id')->constrained('departments')->nullOnDelete();
                $table->index(['clinic_id', 'department_id']);
            }
        });

        Schema::table('medical_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('medical_records', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('patient_id')->constrained('departments')->nullOnDelete();
                $table->index(['clinic_id', 'department_id']);
            }
        });

        Schema::table('doctor_leaves', function (Blueprint $table): void {
            if (! Schema::hasColumn('doctor_leaves', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('doctor_id')->constrained('departments')->nullOnDelete();
                $table->index(['clinic_id', 'department_id', 'leave_date', 'status']);
            }
        });
    }

    private function copyClinicalDepartmentsToClinics(): void
    {
        if (
            ! Schema::hasTable('departments') ||
            ! Schema::hasTable('clinics') ||
            ! Schema::hasColumn('departments', 'clinic_id')
        ) {
            return;
        }

        DB::table('departments')
            ->orderBy('id')
            ->chunk(100, function (Collection $departments): void {
                foreach ($departments as $department) {
                    $name = trim((string) $department->name);

                    if ($name === '') {
                        continue;
                    }

                    $existingClinicId = DB::table('clinics')
                        ->where('name', $name)
                        ->value('id');

                    if ($existingClinicId !== null) {
                        DB::table('departments')
                            ->where('id', $department->id)
                            ->update(['clinic_id' => $existingClinicId]);

                        continue;
                    }

                    $code = $this->uniqueClinicCode($department);

                    $clinicId = DB::table('clinics')->insertGetId([
                        'code' => $code,
                        'name' => $name,
                        'legal_name' => $name,
                        'timezone' => config('app.timezone', 'Asia/Riyadh'),
                        'currency' => config('app.currency', 'SAR'),
                        'phone' => null,
                        'email' => null,
                        'address' => null,
                        'is_active' => (bool) ($department->is_active ?? true),
                        'created_at' => $department->created_at ?? now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('departments')
                        ->where('id', $department->id)
                        ->update(['clinic_id' => $clinicId]);
                }
            });
    }

    private function moveClinicalReferencesToClinics(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $departmentsHaveClinicId = DB::getDriverName() !== 'sqlite'
            && Schema::hasColumn('departments', 'clinic_id');

        if ($departmentsHaveClinicId && Schema::hasColumn('doctor_profiles', 'department_id')) {
            DB::table('doctor_profiles')
                ->join('departments', 'departments.id', '=', 'doctor_profiles.department_id')
                ->whereNotNull('doctor_profiles.department_id')
                ->update(['doctor_profiles.clinic_id' => DB::raw('departments.clinic_id')]);
        }

        if (Schema::hasTable('doctor_schedules')) {
            DB::table('doctor_schedules')
                ->join('doctor_profiles', 'doctor_profiles.user_id', '=', 'doctor_schedules.doctor_id')
                ->update(['doctor_schedules.clinic_id' => DB::raw('doctor_profiles.clinic_id')]);
        }

        if (Schema::hasTable('appointments')) {
            DB::table('appointments')
                ->join('doctor_profiles', 'doctor_profiles.user_id', '=', 'appointments.doctor_id')
                ->whereNotNull('appointments.doctor_id')
                ->update(['appointments.clinic_id' => DB::raw('doctor_profiles.clinic_id')]);
        }

        if (Schema::hasTable('patients')) {
            DB::table('patients')
                ->join('appointments', 'appointments.patient_id', '=', 'patients.id')
                ->update(['patients.clinic_id' => DB::raw('appointments.clinic_id')]);
        }

        if ($departmentsHaveClinicId && Schema::hasColumn('patient_card_visits', 'department_id')) {
            DB::table('patient_card_visits')
                ->join('departments', 'departments.id', '=', 'patient_card_visits.department_id')
                ->whereNotNull('patient_card_visits.department_id')
                ->update(['patient_card_visits.clinic_id' => DB::raw('departments.clinic_id')]);
        }

        if ($departmentsHaveClinicId && Schema::hasColumn('clinic_working_hours', 'department_id')) {
            DB::table('clinic_working_hours')
                ->join('departments', 'departments.id', '=', 'clinic_working_hours.department_id')
                ->whereNotNull('clinic_working_hours.department_id')
                ->update(['clinic_working_hours.clinic_id' => DB::raw('departments.clinic_id')]);
        }

        if ($departmentsHaveClinicId && Schema::hasColumn('medical_records', 'department_id')) {
            DB::table('medical_records')
                ->join('departments', 'departments.id', '=', 'medical_records.department_id')
                ->whereNotNull('medical_records.department_id')
                ->update(['medical_records.clinic_id' => DB::raw('departments.clinic_id')]);
        }

        if ($departmentsHaveClinicId && Schema::hasColumn('doctor_leaves', 'department_id')) {
            DB::table('doctor_leaves')
                ->join('departments', 'departments.id', '=', 'doctor_leaves.department_id')
                ->whereNotNull('doctor_leaves.department_id')
                ->update(['doctor_leaves.clinic_id' => DB::raw('departments.clinic_id')]);
        }
    }

    private function dropClinicalDepartmentReferences(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->dropColumnIfExists('doctor_profiles', 'department_id');
        $this->dropColumnIfExists('patient_card_visits', 'department_id');
        $this->dropColumnIfExists('clinic_working_hours', 'department_id');
        $this->dropColumnIfExists('medical_records', 'department_id');
        $this->dropColumnIfExists('doctor_leaves', 'department_id');
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignKeysForColumn($table, $column);
        $this->dropIndexesForColumn($table, $column);

        Schema::table($table, function (Blueprint $schema) use ($column): void {
            $schema->dropColumn($column);
        });
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $keys = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table, $column],
        );

        foreach ($keys as $key) {
            Schema::table($table, function (Blueprint $schema) use ($key): void {
                $schema->dropForeign($key->CONSTRAINT_NAME);
            });
        }
    }

    private function dropIndexesForColumn(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $indexes = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME != "PRIMARY"',
            [DB::getDatabaseName(), $table, $column],
        );

        foreach ($indexes as $index) {
            Schema::table($table, function (Blueprint $schema) use ($index): void {
                $schema->dropIndex($index->INDEX_NAME);
            });
        }
    }

    private function uniqueClinicCode(object $department): string
    {
        $base = trim((string) ($department->code ?? ''));
        $base = $base !== '' ? $base : 'CLINIC-'.$department->id;
        $base = mb_strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?: 'CLINIC-'.$department->id);
        $code = $base;
        $suffix = 1;

        while (DB::table('clinics')->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
};
