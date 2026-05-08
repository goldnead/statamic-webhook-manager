<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Goldnead\WebhookManager\Templates\TemplateValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOutboundWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CP gates this through middleware/permissions
    }

    public function rules(): array
    {
        $hookId = $this->route('webhook')?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_outbounds', 'handle')->ignore($hookId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['boolean'],
            'trigger_type' => ['required', 'string', 'max:80'],
            'trigger_config' => ['nullable', 'array'],
            'url' => ['required', 'url', 'max:2048'],
            'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'headers' => ['nullable', 'array'],
            'timeout_seconds' => ['integer', 'min:1', 'max:120'],
            'follow_redirects' => ['boolean'],
            'auth_type' => ['required', Rule::in(['none', 'static_header', 'bearer', 'basic', 'hmac'])],
            'auth_config' => ['nullable', 'array'],
            'payload_type' => ['required', Rule::in(['raw_json', 'mapped', 'form'])],
            'payload_template' => ['nullable', 'string'],
            // Optional reference to a library template; if set, takes precedence
            // over the inline payload_template (see HttpRequestFactory::buildBody).
            'payload_template_handle' => [
                'nullable', 'string', 'max:120',
                Rule::exists('webhook_templates', 'handle'),
            ],
            'conditions' => ['nullable', 'array'],
            'retry_strategy' => ['nullable', 'array'],
            'retry_strategy.strategy' => ['nullable', Rule::in(['none', 'linear', 'exponential'])],
            'retry_strategy.max_attempts' => ['nullable', 'integer', 'min:0', 'max:20'],
            'queue_enabled' => ['boolean'],
            'idempotency_enabled' => ['boolean'],
            'log_body_mode' => ['nullable', Rule::in(['full', 'partial', 'none'])],
            'success_matcher' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Skip inline-body validation when the hook delegates to a
            // library template — that body is validated on the Template
            // edit screen instead.
            if ((string) $this->input('payload_template_handle', '') !== '') {
                return;
            }
            $template = (string) $this->input('payload_template', '');
            if ($template === '') {
                return;
            }
            /** @var TemplateValidator $tv */
            $tv = app(TemplateValidator::class);
            $expectJson = $this->input('payload_type') === 'raw_json';
            $result = $tv->check($template, $expectJson);
            foreach ($result['issues'] as $issue) {
                $v->errors()->add('payload_template', $issue);
            }
        });
    }
}
