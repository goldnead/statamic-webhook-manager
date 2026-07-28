<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_secret_audits', function (Blueprint $table) {
            $table->id();
            $table->string('actor_id')->nullable()->index();
            $table->string('target_type'); // outbound | inbound
            $table->unsignedBigInteger('target_id');
            $table->string('action'); // created | rotated | revealed | replaced
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_secret_audits');
    }
};
