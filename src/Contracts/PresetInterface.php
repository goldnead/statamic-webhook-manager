<?php

namespace Goldnead\WebhookManager\Contracts;

/**
 * An integration preset: a recipe that pre-configures an outbound webhook for
 * a popular destination (Slack, Discord, Zapier, …) so the user only supplies
 * a URL + trigger instead of hand-building a payload template and headers.
 *
 * Built-ins are registered in WebhookManagerServiceProvider::bootRegistries();
 * third parties register their own via WebhookManager::registerPreset(...).
 */
interface PresetInterface
{
    /** Unique machine handle, e.g. "slack". */
    public function handle(): string;

    /** Human label shown in the integration gallery. */
    public function label(): string;

    /** Statamic 6 CP icon name. */
    public function icon(): string;

    /** Grouping label, e.g. "Chat" or "Automation". */
    public function category(): string;

    /** One-line description shown on the gallery card. */
    public function description(): string;

    /**
     * Extra setup fields the user must fill, on top of the always-present
     * Name + Trigger. Each entry: ['handle','label','type','instructions'?,
     * 'required'?,'default'?].
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array;

    /**
     * Build the full OutboundWebhook attribute array from the submitted setup
     * input (which includes name, handle, trigger_type + this preset's fields).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array;
}
