<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->timestamp('sample_collected_at')->nullable()->after('ordered_at');
            $table->timestamp('resulted_at')->nullable()->after('sample_collected_at');
            $table->timestamp('canceled_at')->nullable()->after('resulted_at');
            $table->text('cancel_reason')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropColumn(['sample_collected_at', 'resulted_at', 'canceled_at', 'cancel_reason']);
        });
    }
};
