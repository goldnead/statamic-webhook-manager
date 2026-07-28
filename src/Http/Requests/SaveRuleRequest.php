<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for creating/updating an automation rule
 * (trigger, condition tree and action list executed by Rules\RuleEngine).
 */
class SaveRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruleId = $this->route('webhookRule')?->id ?? null;

        // Per-brand, matching `webhook_rules_brand_id_handle_unique`. See
        // SaveOutboundWebhookRequest for why the brand cannot be left implicit.
        $brandId = app('brand-context')->currentId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_rules', 'handle')
                    ->where('brand_id', $brandId)
                    ->ignore($ruleId),
            ],
            'enabled' => ['boolean'],
            'trigger_type' => ['required', 'string', 'max:80'],
            'trigger_config' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'stop_on_failure' => ['boolean'],
            'order_index' => ['integer', 'min:0'],
        ];
    }
}
