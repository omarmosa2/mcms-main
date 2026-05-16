<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'doctor_schedules',
            'patient_allergies',
            'patient_medications',
            'patient_chronic_conditions',
            'patient_attachments',
            'visit_diagnoses',
            'visit_vital_signs',
            'lab_orders',
            'lab_results',
            'lab_test_templates',
            'radiology_orders',
            'radiology_reports',
            'radiology_study_types',
            'radiology_images',
            'prescriptions',
            'prescription_items',
            'pharmacy_drugs',
            'drug_batches',
            'pharmacy_dispenses',
            'pharmacy_dispense_items',
            'suppliers',
            'purchase_orders',
            'purchase_order_items',
            'stock_adjustments',
            'inventory_returns',
            'inventory_alerts',
            'payment_plans',
            'installments',
            'expenses',
            'expense_categories',
            'salaries',
            'cashboxes',
            'accounts',
            'journal_entries',
            'journal_entry_lines',
            'workflows',
            'workflow_steps',
            'workflow_instances',
            'workflow_approvals',
            'number_ranges',
            'branding_settings',
            'security_policies',
            'external_integrations',
            'appointment_reminders',
            'user_invitations',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'doctor_schedules',
            'patient_allergies',
            'patient_medications',
            'patient_chronic_conditions',
            'patient_attachments',
            'visit_diagnoses',
            'visit_vital_signs',
            'lab_orders',
            'lab_results',
            'lab_test_templates',
            'radiology_orders',
            'radiology_reports',
            'radiology_study_types',
            'radiology_images',
            'prescriptions',
            'prescription_items',
            'pharmacy_drugs',
            'drug_batches',
            'pharmacy_dispenses',
            'pharmacy_dispense_items',
            'suppliers',
            'purchase_orders',
            'purchase_order_items',
            'stock_adjustments',
            'inventory_returns',
            'inventory_alerts',
            'payment_plans',
            'installments',
            'expenses',
            'expense_categories',
            'salaries',
            'cashboxes',
            'accounts',
            'journal_entries',
            'journal_entry_lines',
            'workflows',
            'workflow_steps',
            'workflow_instances',
            'workflow_approvals',
            'number_ranges',
            'branding_settings',
            'security_policies',
            'external_integrations',
            'appointment_reminders',
            'user_invitations',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
