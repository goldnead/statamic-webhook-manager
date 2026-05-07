<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbounds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('handle')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);

            $table->string('path');
            $table->json('allowed_methods')->nullable();

            $table->string('auth_type')->default('static_header');
            $table->text('auth_config')->nullable(); // encrypted json

            $table->string('expected_content_type')->default('application/json');
            $table->unsignedInteger('max_payload_kb')->default(512);

            $table->boolean('replay_protection_enabled')->default(false);
            $table->json('rate_limit_config')->nullable();
            $table->string('logging_mode')->default('partial');

            $table->json('mapping_config')->nullable();

            $table->string('action_type')->default('noop'); // noop | create_entry | update_entry | …
            $table->json('action_config')->nullable();

            $table->json('response_config')->nullable();

            $table->timestamps();

            $table->index(['enabled', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbounds');
    }
};
