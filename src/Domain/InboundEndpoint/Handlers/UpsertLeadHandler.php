<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

/**
 * Creates or updates a LeadHub contact (and a timeline entry) from an inbound
 * webhook — e.g. a payment provider or booking tool POSTing customer events.
 *
 * Requires goldnead/statamic-leadhub. When that addon is absent the handler is
 * a graceful no-op-with-error, so the Webhook Manager keeps zero hard
 * dependency on LeadHub.
 *
 * Endpoint `action_config` keys (all optional unless noted):
 *   - `email_field`      mapped-payload key holding the email (default "email")
 *   - `type`             timeline event type (default "webhook.received")
 *   - `summary`          timeline summary
 *   - `source`           contact source label
 *   - `tags`             array of tag names to attach
 *   - `dedupe_key_field` mapped-payload key holding an idempotency key
 *   - `pipeline`         when set, also upsert an opportunity in this pipeline
 *   - `mode`             "ingest" (default, contact + timeline) | "upsert" (contact only)
 */
class UpsertLeadHandler implements InboundActionHandlerInterface
{
    /** The LeadHub facade FQCN (lowercase "hub" PSR-4 namespace). */
    protected const LEADHUB_FACADE = '\\Goldnead\\Leadhub\\Facades\\LeadHub';

    public function handle(): string
    {
        return 'upsert_lead';
    }

    public function label(): string
    {
        return 'Create or update LeadHub lead';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $facade = static::LEADHUB_FACADE;

        if (! class_exists($facade)) {
            return [
                'ok' => false,
                'message' => 'LeadHub (goldnead/statamic-leadhub) is not installed.',
                'data' => [],
            ];
        }

        $config = $endpoint->action_config ?? [];
        $emailField = (string) ($config['email_field'] ?? 'email');
        $email = $mappedPayload[$emailField] ?? null;

        if (empty($email)) {
            return [
                'ok' => false,
                'message' => "Inbound payload has no email at '{$emailField}'.",
                'data' => [],
            ];
        }

        try {
            $contactFields = $this->extractContactFields($mappedPayload);
            $mode = (string) ($config['mode'] ?? 'ingest');

            if ($mode === 'upsert') {
                $lead = $facade::create(array_merge(['email' => $email], $contactFields, [
                    'source' => $config['source'] ?? 'inbound_webhook',
                    'tags' => $config['tags'] ?? [],
                ]));
                $leadId = $lead['id'] ?? null;
            } else {
                $dedupeKey = isset($config['dedupe_key_field'])
                    ? ($mappedPayload[$config['dedupe_key_field']] ?? null)
                    : null;

                $event = $facade::ingest([
                    'email' => $email,
                    'type' => $config['type'] ?? 'webhook.received',
                    'summary' => $config['summary'] ?? 'Inbound webhook received',
                    'source' => $config['source'] ?? 'inbound_webhook',
                    'source_type' => 'webhook_manager.inbound',
                    'source_id' => $endpoint->getKey(),
                    'dedupe_key' => $dedupeKey,
                    'payload' => $mappedPayload,
                    'contact' => $contactFields,
                    'tags' => $config['tags'] ?? [],
                ]);

                $lead = $facade::findByEmail($email);
                $leadId = $lead['id'] ?? null;
            }

            // Optionally project a pipeline opportunity.
            if ($leadId && ! empty($config['pipeline'])) {
                $facade::upsertOpportunity($leadId, (string) $config['pipeline'], array_filter([
                    'source_type' => 'webhook_manager.inbound',
                    'source_id' => $endpoint->getKey(),
                    'value_estimate' => $mappedPayload['value'] ?? $mappedPayload['amount'] ?? null,
                ], fn ($v) => $v !== null));
            }

            return [
                'ok' => true,
                'message' => 'Lead upserted.',
                'data' => array_filter([
                    'lead_id' => $leadId,
                    'email' => $email,
                ], fn ($v) => $v !== null),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to upsert lead: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Pull the recognised contact fields out of the mapped payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extractContactFields(array $payload): array
    {
        $keys = ['first_name', 'last_name', 'full_name', 'phone', 'company'];

        $fields = [];
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                $fields[$key] = $payload[$key];
            }
        }

        return $fields;
    }
}
