<?php

use Goldnead\WebhookManager\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings that an operator changed from the Control Panel.
 *
 * The table holds *overrides only*, one row per changed key — not a copy of the
 * config file. That is what makes an untouched install behave exactly as it did
 * before this table existed, and what lets a key that is never edited keep
 * following the package default when the default changes in a later release. A
 * settings row that mirrored every key would freeze the shipped defaults at
 * whatever they were on the day somebody first opened the screen.
 *
 * Deliberately **not** brand-scoped, unlike every other table in this addon.
 * These are properties of the installation: how long a delivery may take, how
 * often it is retried, which header carries the signature, how many days of
 * delivery history are kept. A timeout that differed per brand would mean the
 * same queue worker applied different HTTP rules depending on which tenant's
 * delivery it happened to pick up, with nothing on screen to say so — and the
 * feature toggles are worse still, since they gate route and navigation
 * registration, which happens once per boot and cannot vary per brand at all.
 *
 * Always in the database, even on a `flat` install. `storage.driver` decides
 * where the webhook *configuration* lives; deliveries and logs are runtime
 * state and are database-only regardless, and this table joins them. An install
 * that never ran `php artisan migrate` therefore has no settings table — which
 * is handled, not fatal: {@see Settings::overrides()} answers "no overrides",
 * and the config file keeps being the whole truth.
 *
 * `key` is the dotted path under `webhook-manager.` (`retry.max_attempts`), so
 * a row says where it applies without a mapping table in between. `value` is
 * JSON because the values are not all scalars — `logging.mask_headers` and
 * `retry.retry_on_status` are lists — and because an integer has to come back
 * an integer for "equals the packaged default" to hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_settings');
    }
};
