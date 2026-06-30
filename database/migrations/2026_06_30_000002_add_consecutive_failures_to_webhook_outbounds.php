<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            // Circuit breaker: consecutive terminal failures, reset on success.
            $table->unsignedInteger('consecutive_failures')->default(0)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            $table->dropColumn('consecutive_failures');
        });
    }
};
