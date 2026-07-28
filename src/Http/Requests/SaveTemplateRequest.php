<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('template')?->id ?? null;

        // Per-brand, matching `webhook_templates_brand_id_handle_unique`. See
        // SaveOutboundWebhookRequest for why the brand cannot be left implicit.
        $brandId = app('brand-context')->currentId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_templates', 'handle')
                    ->where('brand_id', $brandId)
                    ->ignore($templateId),
            ],
            'type' => ['required', Rule::in(['outbound_body', 'inbound_response', 'notification'])],
            'body' => ['required', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
