<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('level')->default('info'); // debug | info | warning | error
            $table->string('type')->index();

            $table->unsignedBigInteger('related_webhook_id')->nullable()->index();
            $table->unsignedBigInteger('related_endpoint_id')->nullable()->index();
            $table->unsignedBigInteger('related_delivery_id')->nullable()->index();

            $table->string('correlation_id', 64)->nullable()->index();

            $table->text('message');
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
