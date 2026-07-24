<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1 brand-scoping for Webhook Manager.
 *
 * Adds `brand_id` to every stateful table and hardens uniqueness so the same
 * logical identifier (handle) can exist independently per brand. Root tables
 * (outbounds, inbounds, rules, templates) carry brand_id directly; child tables
 * (deliveries, logs, secret_audits) carry it denormalised for query-time
 * defense — every read filters on brand_id instead of trusting a join.
 *
 * Backfill assigns all existing rows to the default brand ("Bestand = Default").
 * Child rows inherit brand_id from their parent where resolvable, else default.
 *
 * Note on idempotency: the outbound delivery `idempotency_key` is indexed, not
 * unique, and inbound replay/idempotency keys live in the cache
 * (ReplayProtectionService), keyed by endpoint id — never a global DB unique.
 * So there is no global-unique idempotency constraint to brand-scope; we only
 * add a composite (brand_id, idempotency_key) index for query-time isolation.
 */
return new class extends Migration
{
    /** Root tables: brand_id + handle-unique rework. */
    private array $rootTables = [
        'webhook_outbounds',
        'webhook_inbounds',
        'webhook_rules',
        'webhook_templates',
    ];

    public function up(): void
    {
        $defaultId = (int) (DB::table('brands')->where('is_default', true)->value('id') ?? 1);

        // 1. Add nullable brand_id + index everywhere.
        foreach ([...$this->rootTables, 'webhook_deliveries', 'webhook_logs', 'webhook_secret_audits'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('id')->index();
            });
        }

        // 2. Backfill root tables → default brand.
        foreach ($this->rootTables as $t) {
            DB::table($t)->update(['brand_id' => $defaultId]);
        }

        // 3. Backfill child tables — inherit from parent, else default.
        // Deliveries inherit from their outbound webhook.
        DB::table('webhook_deliveries')->update([
            'brand_id' => DB::raw(
                '(SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_deliveries.outbound_webhook_id)'
            ),
        ]);

        // Logs inherit from the related outbound webhook, else the related inbound endpoint.
        DB::table('webhook_logs')->update([
            'brand_id' => DB::raw(
                'COALESCE('.
                '(SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_logs.related_webhook_id),'.
                '(SELECT i.brand_id FROM webhook_inbounds i WHERE i.id = webhook_logs.related_endpoint_id)'.
                ')'
            ),
        ]);

        // Secret audits inherit from their polymorphic target.
        DB::table('webhook_secret_audits')->update([
            'brand_id' => DB::raw(
                "CASE target_type ".
                "WHEN 'outbound' THEN (SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_secret_audits.target_id) ".
                "WHEN 'inbound' THEN (SELECT i.brand_id FROM webhook_inbounds i WHERE i.id = webhook_secret_audits.target_id) ".
                "ELSE NULL END"
            ),
        ]);

        // Any child row whose parent was missing → default brand (never leave null).
        foreach (['webhook_deliveries', 'webhook_logs', 'webhook_secret_audits'] as $t) {
            DB::table($t)->whereNull('brand_id')->update(['brand_id' => $defaultId]);
        }

        // 4. Enforce NOT NULL now that every row is backfilled.
        foreach ([...$this->rootTables, 'webhook_deliveries', 'webhook_logs', 'webhook_secret_audits'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->nullable(false)->change();
            });
        }

        // 5. Unique-rework: handle is unique per brand, not globally.
        foreach ($this->rootTables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropUnique($t.'_handle_unique');
                $table->unique(['brand_id', 'handle']);
            });
        }

        // 6. Query-time isolation index for outbound delivery idempotency keys.
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index(['brand_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'idempotency_key']);
        });

        foreach ($this->rootTables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropUnique(['brand_id', 'handle']);
                $table->unique('handle');
            });
        }

        foreach ([...$this->rootTables, 'webhook_deliveries', 'webhook_logs', 'webhook_secret_audits'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropIndex($t.'_brand_id_index');
                $table->dropColumn('brand_id');
            });
        }
    }
};
