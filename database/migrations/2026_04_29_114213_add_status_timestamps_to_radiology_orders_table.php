<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('ordered_at');
            $table->timestamp('reported_at')->nullable()->after('completed_at');
            $table->timestamp('canceled_at')->nullable()->after('reported_at');
            $table->text('cancel_reason')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('radiology_orders', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'reported_at', 'canceled_at', 'cancel_reason']);
        });
    }
};
