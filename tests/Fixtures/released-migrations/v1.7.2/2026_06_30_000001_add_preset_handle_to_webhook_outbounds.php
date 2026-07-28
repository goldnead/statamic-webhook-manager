<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            // Which integration preset created this webhook (null = built by hand).
            $table->string('preset_handle')->nullable()->after('handle')->index();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            $table->dropIndex(['preset_handle']);
            $table->dropColumn('preset_handle');
        });
    }
};
