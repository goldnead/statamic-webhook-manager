<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Registries\PresetRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Integration presets: a guided "pick a destination → fill a URL → done" flow
 * that builds a fully-configured OutboundWebhook from a preset recipe, so users
 * never hand-write a payload template for Slack/Discord/Zapier/etc.
 */
class PresetController extends CpController
{
    public function index(Request $request, PresetRegistry $presets)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        return Inertia::render('webhook-manager::Integrations/Index', [
            'presets' => $presets->gallery(),
            'setupUrlBase' => cp_route('webhook-manager.integrations.create', ['preset' => '__PRESET__']),
            'outboundUrl' => cp_route('webhook-manager.outbound.index'),
        ]);
    }

    public function create(Request $request, PresetRegistry $presets, TriggerRegistry $triggers, string $preset)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $instance = $presets->get($preset);
        abort_if($instance === null, 404);

        return Inertia::render('webhook-manager::Integrations/Setup', [
            'preset' => [
                'handle' => $instance->handle(),
                'label' => $instance->label(),
                'icon' => $instance->icon(),
                'category' => $instance->category(),
                'description' => $instance->description(),
                'fields' => $instance->fields(),
            ],
            'triggerOptions' => $triggers->options(),
            'galleryUrl' => cp_route('webhook-manager.integrations.index'),
            'saveUrl' => cp_route('webhook-manager.integrations.store', ['preset' => $instance->handle()]),
        ]);
    }

    public function store(Request $request, PresetRegistry $presets, CreateOutboundWebhookAction $create, string $preset)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $instance = $presets->get($preset);
        abort_if($instance === null, 404);

        // Minimal validation: Name + Trigger + each required preset field.
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', 'string', 'max:80'],
        ];
        foreach ($instance->fields() as $field) {
            $rules[$field['handle']] = ($field['required'] ?? false) ? ['required'] : ['nullable'];
            if (($field['handle'] ?? null) === $instance->handle() || in_array($field['type'] ?? 'text', ['text'], true)) {
                // URL-ish fields are validated as URLs when required.
                if (str_contains($field['handle'], 'url')) {
                    $rules[$field['handle']][] = 'url';
                }
            }
        }
        $input = $request->validate($rules);

        $input['handle'] = $this->uniqueHandle($input['name']);

        $attributes = $instance->build($input);
        $hook = $create($attributes);

        return redirect(cp_route('webhook-manager.outbound.edit', $hook))
            ->with('success', __('webhook-manager::messages.integration_created', ['name' => $hook->name]));
    }

    /** Generate a unique slug handle, appending -2, -3, … on collision. */
    protected function uniqueHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'webhook';
        $handle = $base;
        $i = 1;
        while (app(OutboundWebhookRepositoryInterface::class)->findByHandle($handle) !== null) {
            $i++;
            $handle = "{$base}-{$i}";
        }

        return $handle;
    }

    private function authorizeOr403(Request $request, string $ability): void
    {
        abort_unless($request->user()?->can($ability), 403);
    }
}
