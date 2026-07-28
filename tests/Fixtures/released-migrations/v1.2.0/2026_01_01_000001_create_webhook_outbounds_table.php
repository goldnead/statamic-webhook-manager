<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_outbounds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('handle')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);

            // Trigger
            $table->string('trigger_type')->index(); // e.g. entry.published
            $table->json('trigger_config')->nullable();

            // Destination
            $table->string('url');
            $table->string('method')->default('POST');
            $table->json('headers')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->boolean('follow_redirects')->default(true);

            // Auth
            $table->string('auth_type')->default('none');
            $table->text('auth_config')->nullable(); // encrypted json

            // Payload
            $table->string('payload_type')->default('raw_json'); // raw_json | mapped | form
            $table->longText('payload_template')->nullable();

            // Conditions
            $table->json('conditions')->nullable();

            // Retry / queue
            $table->json('retry_strategy')->nullable();
            $table->boolean('queue_enabled')->default(true);

            // Idempotency / dedupe
            $table->boolean('idempotency_enabled')->default(false);
            $table->string('idempotency_strategy')->nullable();

            // Logging
            $table->string('log_body_mode')->default('partial'); // full | partial | none

            // Custom success matcher (optional override)
            $table->json('success_matcher')->nullable();

            $table->timestamps();

            $table->index(['enabled', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_outbounds');
    }
};
