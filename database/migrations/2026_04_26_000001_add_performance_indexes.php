<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'email') || ! $this->hasIndex('patients', 'patients_email_index')) {
                $table->index('email', 'patients_email_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->hasIndex('invoices', 'invoices_issued_at_index')) {
                $table->index('issued_at', 'invoices_issued_at_index');
            }
            if (! $this->hasIndex('invoices', 'invoices_clinic_id_status_index')) {
                $table->index(['clinic_id', 'status'], 'invoices_clinic_id_status_index');
            }
        });

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'clinic_id')) {
                    $table->foreignId('clinic_id')->nullable()->after('invoice_id')->constrained('clinics')->cascadeOnDelete();
                }
                if (! $this->hasIndex('payments', 'payments_clinic_id_index')) {
                    $table->index('clinic_id', 'payments_clinic_id_index');
                }
                if (! $this->hasIndex('payments', 'payments_invoice_id_index')) {
                    $table->index('invoice_id', 'payments_invoice_id_index');
                }
                if (! $this->hasIndex('payments', 'payments_paid_at_index')) {
                    $table->index('paid_at', 'payments_paid_at_index');
                }
                if (! $this->hasIndex('payments', 'payments_status_index')) {
                    $table->index('status', 'payments_status_index');
                }
                if (! $this->hasIndex('payments', 'payments_refunded_at_index')) {
                    $table->index('refunded_at', 'payments_refunded_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_email_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_issued_at_index');
            $table->dropIndex('invoices_clinic_id_status_index');
        });

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_clinic_id_index');
                $table->dropIndex('payments_invoice_id_index');
                $table->dropIndex('payments_paid_at_index');
                $table->dropIndex('payments_status_index');
                $table->dropIndex('payments_refunded_at_index');
                if (Schema::hasColumn('payments', 'clinic_id')) {
                    $table->dropForeign(['clinic_id']);
                    $table->dropColumn('clinic_id');
                }
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(fn ($index) => $index['name'] === $indexName);
    }
};
