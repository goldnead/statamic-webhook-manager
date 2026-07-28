<?php

namespace Goldnead\WebhookManager\Tests\Fixtures;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Real-shaped webhook data, insertable into any released schema.
 *
 * A migration test is only worth running against rows, and rows are the one
 * thing this addon's migration coverage never had. The awkward part is that the
 * schema those rows go into changes underneath them — `payload_template_handle`
 * appears in 1.1.0, `preset_handle` and `consecutive_failures` in 1.3.0,
 * `brand_id` in 1.3.0 — so a fixture with a fixed column list can only seed one
 * version of the database.
 *
 * This one asks the schema what it has. Every row is built at its widest, then
 * reduced to the columns that exist at the moment of the insert, and anything
 * NOT NULL that the fixture does not know about is filled generically and
 * uniquely per row, so a migration added next year is seeded without this file
 * being touched.
 *
 * The shape is the one the brand-scoping migration has to survive: three
 * outbound webhooks, two inbound endpoints, two rules and two templates, plus
 * deliveries, logs and secret audits that actually point at those parents. The
 * children matter — the migration resolves their brand by walking back to the
 * parent row, and a child whose parent id is invented would take the "parent
 * missing, fall back to default" path instead and prove nothing.
 */
class WebhookManagerDataFixture
{
    /**
     * @var list<array{handle: string, name: string, trigger_type: string, url: string}>
     */
    public const OUTBOUNDS = [
        ['handle' => 'crm-lead', 'name' => 'CRM Lead', 'trigger_type' => 'entry.published', 'url' => 'https://crm.example.com/hooks/lead'],
        ['handle' => 'slack-alert', 'name' => 'Slack Alert', 'trigger_type' => 'entry.deleted', 'url' => 'https://hooks.slack.example.com/services/T000/B000'],
        ['handle' => 'zapier-catch', 'name' => 'Zapier Catch', 'trigger_type' => 'form.submitted', 'url' => 'https://hooks.zapier.example.com/catch/1/abcdef'],
    ];

    /**
     * @var list<array{handle: string, name: string, path: string}>
     */
    public const INBOUNDS = [
        ['handle' => 'stripe-events', 'name' => 'Stripe Events', 'path' => 'stripe-events'],
        ['handle' => 'typeform-intake', 'name' => 'Typeform Intake', 'path' => 'typeform-intake'],
    ];

    /**
     * @var list<array{handle: string, name: string, trigger_type: string}>
     */
    public const RULES = [
        ['handle' => 'publish-fanout', 'name' => 'Publish Fanout', 'trigger_type' => 'entry.published'],
        ['handle' => 'form-router', 'name' => 'Form Router', 'trigger_type' => 'form.submitted'],
    ];

    /**
     * @var list<array{handle: string, name: string, type: string, body: string}>
     */
    public const TEMPLATES = [
        ['handle' => 'lead-json', 'name' => 'Lead JSON', 'type' => 'outbound_body', 'body' => '{"email": "{{ email }}"}'],
        ['handle' => 'ack-response', 'name' => 'Ack Response', 'type' => 'inbound_response', 'body' => '{"ok": true}'],
    ];

    /**
     * The outbound handle used to prove the handle unique still bites. Real
     * fixture data rather than a row invented for the assertion.
     */
    public const HANDLE_PROBE = 'crm-lead';

    public function __construct(private Connection $connection) {}

    /**
     * The outbound handle to probe a given seed batch with.
     */
    public static function handleProbe(int $batch = 0): string
    {
        return self::HANDLE_PROBE.self::suffix($batch);
    }

    public static function suffix(int $batch): string
    {
        return $batch === 0 ? '' : '-b'.$batch;
    }

    /**
     * Put one full generation of data into every webhook table that exists.
     *
     * Repeatable: pass a different `$batch` to add another generation without
     * colliding with the last one. Batch 0 is the fixture above verbatim, so
     * assertions can name a row by hand.
     *
     * @return int the number of rows written
     */
    public function seed(int $batch = 0): int
    {
        $suffix = self::suffix($batch);
        $written = 0;

        $outboundIds = [];
        $inboundIds = [];

        if ($this->has('webhook_outbounds')) {
            foreach (self::OUTBOUNDS as $outbound) {
                $outboundIds[] = $this->insert('webhook_outbounds', [
                    'uuid' => (string) Str::uuid(),
                    'name' => $outbound['name'],
                    'handle' => $outbound['handle'].$suffix,
                    'description' => 'Seeded by '.self::class.'.',
                    'enabled' => 1,
                    'trigger_type' => $outbound['trigger_type'],
                    'trigger_config' => json_encode(['collection' => 'pages']),
                    'url' => $outbound['url'],
                    'method' => 'POST',
                    'headers' => json_encode(['X-Source' => 'fixture']),
                    'auth_type' => 'none',
                    'payload_type' => 'raw_json',
                    'payload_template' => '{"id": "{{ id }}"}',
                    'payload_template_handle' => null,
                    'preset_handle' => null,
                    'consecutive_failures' => 0,
                    'queue_enabled' => 1,
                    'idempotency_enabled' => 0,
                    'log_body_mode' => 'partial',
                ]);
            }

            $written += count($outboundIds);
        }

        if ($this->has('webhook_inbounds')) {
            foreach (self::INBOUNDS as $inbound) {
                $inboundIds[] = $this->insert('webhook_inbounds', [
                    'uuid' => (string) Str::uuid(),
                    'name' => $inbound['name'],
                    'handle' => $inbound['handle'].$suffix,
                    'description' => null,
                    'enabled' => 1,
                    'path' => $inbound['path'].$suffix,
                    'allowed_methods' => json_encode(['POST']),
                    'auth_type' => 'static_header',
                    'expected_content_type' => 'application/json',
                    'max_payload_kb' => 512,
                    'logging_mode' => 'partial',
                    'action_type' => 'noop',
                ]);
            }

            $written += count($inboundIds);
        }

        if ($this->has('webhook_rules')) {
            foreach (self::RULES as $index => $rule) {
                $this->insert('webhook_rules', [
                    'uuid' => (string) Str::uuid(),
                    'name' => $rule['name'],
                    'handle' => $rule['handle'].$suffix,
                    'enabled' => 1,
                    'trigger_type' => $rule['trigger_type'],
                    'trigger_config' => json_encode([]),
                    'conditions' => json_encode([]),
                    'actions' => json_encode([['type' => 'send_webhook', 'handle' => 'crm-lead'.$suffix]]),
                    'stop_on_failure' => 0,
                    'order_index' => $index,
                ]);

                $written++;
            }
        }

        if ($this->has('webhook_templates')) {
            foreach (self::TEMPLATES as $template) {
                $this->insert('webhook_templates', [
                    'uuid' => (string) Str::uuid(),
                    'name' => $template['name'],
                    'handle' => $template['handle'].$suffix,
                    'type' => $template['type'],
                    'body' => $template['body'],
                    'meta' => json_encode(['source' => 'fixture']),
                ]);

                $written++;
            }
        }

        // Children, pointed at the parents above so the inheritance backfill
        // has something real to resolve.
        if ($this->has('webhook_deliveries') && $outboundIds !== []) {
            foreach (['success', 'failed', 'pending', 'success', 'failed'] as $index => $status) {
                $this->insert('webhook_deliveries', [
                    'uuid' => (string) Str::uuid(),
                    'outbound_webhook_id' => $outboundIds[$index % count($outboundIds)],
                    'rule_id' => null,
                    'trigger_type' => 'entry.published',
                    'trigger_reference' => 'entry::'.Str::uuid(),
                    'status' => $status,
                    'request_url' => 'https://crm.example.com/hooks/lead',
                    'request_method' => 'POST',
                    'request_headers' => json_encode(['Content-Type' => 'application/json']),
                    'request_body' => '{"id": "1"}',
                    'response_status' => $status === 'success' ? 200 : null,
                    'attempts' => 1,
                    'correlation_id' => substr(hash('sha256', 'correlation'.$index.$suffix), 0, 32),
                    'idempotency_key' => substr(hash('sha256', 'idempotency'.$index.$suffix), 0, 64),
                    'rendered_from_snapshot' => 1,
                ]);

                $written++;
            }
        }

        if ($this->has('webhook_logs') && $outboundIds !== []) {
            foreach ($outboundIds as $outboundId) {
                $this->insert('webhook_logs', [
                    'uuid' => (string) Str::uuid(),
                    'level' => 'info',
                    'type' => 'outbound.sent',
                    'related_webhook_id' => $outboundId,
                    'related_endpoint_id' => null,
                    'related_delivery_id' => null,
                    'message' => 'Delivered.',
                    'context' => json_encode(['fixture' => true]),
                ]);

                $written++;
            }

            foreach ($inboundIds as $inboundId) {
                $this->insert('webhook_logs', [
                    'uuid' => (string) Str::uuid(),
                    'level' => 'warning',
                    'type' => 'inbound.rejected',
                    'related_webhook_id' => null,
                    'related_endpoint_id' => $inboundId,
                    'related_delivery_id' => null,
                    'message' => 'Signature mismatch.',
                    'context' => json_encode(['fixture' => true]),
                ]);

                $written++;
            }
        }

        if ($this->has('webhook_secret_audits')) {
            foreach ($outboundIds as $outboundId) {
                $this->insert('webhook_secret_audits', [
                    'actor_id' => 'fixture-user',
                    'target_type' => 'outbound',
                    'target_id' => $outboundId,
                    'action' => 'rotated',
                    'context' => json_encode(['fixture' => true]),
                ]);

                $written++;
            }

            foreach ($inboundIds as $inboundId) {
                $this->insert('webhook_secret_audits', [
                    'actor_id' => 'fixture-user',
                    'target_type' => 'inbound',
                    'target_id' => $inboundId,
                    'action' => 'created',
                    'context' => json_encode(['fixture' => true]),
                ]);

                $written++;
            }
        }

        return $written;
    }

    /**
     * How many rows every webhook table currently holds.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (self::tables() as $table) {
            if ($this->has($table)) {
                $counts[$table] = $this->connection->table($table)->count();
            }
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'webhook_outbounds',
            'webhook_inbounds',
            'webhook_rules',
            'webhook_templates',
            'webhook_deliveries',
            'webhook_logs',
            'webhook_secret_audits',
        ];
    }

    private function has(string $table): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($table);
    }

    /**
     * Reduce a row to the columns the table has today, add timestamps, stamp the
     * brand where the column exists, fill any NOT NULL column the fixture does
     * not know about, and insert.
     *
     * @param  array<string, mixed>  $row
     */
    private function insert(string $table, array $row): int
    {
        $columns = collect($this->connection->getSchemaBuilder()->getColumns($table))
            ->keyBy('name');

        $row = collect($row)
            ->only($columns->keys()->all())
            ->all();

        if ($columns->has('created_at')) {
            $row['created_at'] = now();
        }

        if ($columns->has('updated_at')) {
            $row['updated_at'] = now();
        }

        if ($columns->has('brand_id') && ! isset($row['brand_id'])) {
            $row['brand_id'] = $this->defaultBrandId();
        }

        foreach ($columns as $name => $column) {
            if (array_key_exists($name, $row)) {
                continue;
            }

            if (($column['auto_increment'] ?? false) || ($column['nullable'] ?? true) || ($column['default'] ?? null) !== null) {
                continue;
            }

            $row[$name] = $this->genericValueFor($column, $table, $name);
        }

        return (int) $this->connection->table($table)->insertGetId($row);
    }

    /**
     * A value for a NOT NULL column this fixture has never heard of.
     *
     * Unique per row, because a column added by a future migration is most
     * likely to be added together with a unique over it — which is the shape
     * this whole file exists to catch.
     *
     * @param  array<string, mixed>  $column
     */
    private function genericValueFor(array $column, string $table, string $name): string|int
    {
        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? 'string'));

        return match (true) {
            str_contains($type, 'int') => random_int(1, 2147483647),
            str_contains($type, 'bool') => 0,
            str_contains($type, 'date'), str_contains($type, 'time') => (string) now(),
            default => substr(hash('sha256', $table.$name.Str::uuid()), 0, 32),
        };
    }

    private function defaultBrandId(): ?int
    {
        if (! $this->connection->getSchemaBuilder()->hasTable('brands')) {
            return null;
        }

        $id = $this->connection->table('brands')->where('is_default', true)->min('id')
            ?? $this->connection->table('brands')->min('id');

        return $id === null ? null : (int) $id;
    }
}
