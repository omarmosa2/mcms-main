<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'expense_number')) {
                $table->string('expense_number', 50)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('expenses', 'title')) {
                $table->string('title', 255)->nullable()->after('category_id');
            }
            if (! Schema::hasColumn('expenses', 'paid_to')) {
                $table->string('paid_to', 255)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('expenses', 'reference_number')) {
                $table->string('reference_number', 100)->nullable()->after('paid_to');
            }
            if (! Schema::hasColumn('expenses', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('clinic_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('expenses', 'approved_by')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            });
        }

        if (Schema::hasColumn('expenses', 'approved_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending'");
            DB::statement('ALTER TABLE expenses MODIFY COLUMN description TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
            if (Schema::hasColumn('expenses', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('expenses', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
            if (Schema::hasColumn('expenses', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
            if (Schema::hasColumn('expenses', 'paid_to')) {
                $table->dropColumn('paid_to');
            }
            if (Schema::hasColumn('expenses', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('expenses', 'expense_number')) {
                $table->dropColumn('expense_number');
            }
            if (! Schema::hasColumn('expenses', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'approved_at')) {
                $table->dateTime('approved_at')->nullable();
            }
        });
    }
};
