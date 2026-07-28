<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('handle')->unique();
            $table->boolean('enabled')->default(true);

            $table->string('trigger_type')->index();
            $table->json('trigger_config')->nullable();

            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();

            $table->boolean('stop_on_failure')->default(false);
            $table->unsignedInteger('order_index')->default(0);

            $table->timestamps();

            $table->index(['enabled', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_rules');
    }
};
