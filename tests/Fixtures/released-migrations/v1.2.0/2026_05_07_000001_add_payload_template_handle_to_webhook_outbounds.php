<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional `payload_template_handle` column to outbound webhooks
 * so a hook can reuse a library template instead of carrying its own
 * inline `payload_template`. Either source is valid; the request factory
 * prefers the library template when both are set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            $table->string('payload_template_handle')->nullable()->after('payload_template');
            $table->index('payload_template_handle');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_outbounds', function (Blueprint $table) {
            $table->dropIndex(['payload_template_handle']);
            $table->dropColumn('payload_template_handle');
        });
    }
};
