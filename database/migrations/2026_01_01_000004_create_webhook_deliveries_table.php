<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('outbound_webhook_id')->nullable()->index();
            $table->unsignedBigInteger('rule_id')->nullable()->index();

            $table->string('trigger_type')->index();
            $table->string('trigger_reference')->nullable();

            $table->string('status')->default('pending')->index();
            // pending | processing | success | failed | cancelled

            $table->string('request_url', 2048);
            $table->string('request_method', 10);
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            $table->string('error_type')->nullable()->index();
            $table->text('error_message')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();

            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('idempotency_key', 128)->nullable()->index();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('rendered_from_snapshot')->default(true);

            $table->timestamps();

            $table->index(['status', 'created_at']);
            // TODO: REVIEW — request_body / response_body could be compressed
            // or offloaded to object storage if deliveries grow very large.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
