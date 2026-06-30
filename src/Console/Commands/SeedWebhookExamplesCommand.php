<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Illuminate\Console\Command;

class SeedWebhookExamplesCommand extends Command
{
    protected $signature = 'webhook-manager:seed-examples
        {--force : Recreate fixtures even if they already exist}';

    protected $description = 'Install sample outbound hooks and templates for local development.';

    public function __construct(
        protected OutboundWebhookRepositoryInterface $outbounds,
        protected TemplateRepositoryInterface $templates,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->seedTemplates($force);
        $this->seedOutboundHooks($force);

        $this->info('Sample fixtures installed. Visit Webhooks → Outbound in the CP.');
        return self::SUCCESS;
    }

    protected function seedTemplates(bool $force): void
    {
        $templates = [
            [
                'name' => 'Entry published — JSON',
                'handle' => 'entry-published-json',
                'type' => Template::TYPE_OUTBOUND_BODY,
                'body' => json_encode([
                    'event' => 'entry.published',
                    'id' => '{{ entry:id }}',
                    'title' => '{{ entry:title }}',
                    'collection' => '{{ entry:collection }}',
                    'site' => '{{ site:handle }}',
                    'updated_at' => '{{ system:timestamp_iso }}',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
            [
                'name' => 'Form submission — JSON',
                'handle' => 'form-submission-json',
                'type' => Template::TYPE_OUTBOUND_BODY,
                'body' => json_encode([
                    'event' => 'form.submitted',
                    'form' => '{{ form_submission:form }}',
                    'data' => '{{ payload:data }}',
                    'submitted_at' => '{{ system:timestamp_iso }}',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        ];

        foreach ($templates as $tpl) {
            $existing = $this->templates->findByHandle($tpl['handle']);
            if ($existing && ! $force) {
                continue;
            }
            if ($existing) {
                $this->templates->delete($existing);
            }
            $this->templates->create($tpl);
        }
    }

    protected function seedOutboundHooks(bool $force): void
    {
        $hooks = [
            [
                'name' => 'Notify CRM on entry publish',
                'handle' => 'crm-on-entry-publish',
                'description' => 'Sample webhook firing on entry.published.',
                'enabled' => false,
                'trigger_type' => 'entry.published',
                'url' => 'https://example.com/hooks/entry-published',
                'method' => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'auth_type' => 'none',
                'payload_type' => 'raw_json',
                'payload_template' => json_encode([
                    'event' => 'entry.published',
                    'id' => '{{ entry:id }}',
                    'title' => '{{ entry:title }}',
                    'collection' => '{{ entry:collection }}',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'queue_enabled' => true,
            ],
            [
                'name' => 'Forward form submissions',
                'handle' => 'forward-form-submissions',
                'description' => 'Sample webhook firing on form.submitted.',
                'enabled' => false,
                'trigger_type' => 'form.submitted',
                'url' => 'https://example.com/hooks/forms',
                'method' => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'auth_type' => 'hmac',
                'payload_type' => 'raw_json',
                'payload_template' => json_encode([
                    'form' => '{{ form_submission:form }}',
                    'submitted_at' => '{{ system:timestamp_iso }}',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'queue_enabled' => true,
                'auth_config' => ['secret' => 'change-me', 'algorithm' => 'sha256'],
            ],
        ];

        foreach ($hooks as $h) {
            $existing = $this->outbounds->findByHandle($h['handle']);
            if ($existing && ! $force) {
                continue;
            }
            if ($existing) {
                $this->outbounds->delete($existing);
            }
            $this->outbounds->create($h);
        }
    }
}
