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

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_templates', 'handle')->ignore($templateId),
            ],
            'type' => ['required', Rule::in(['outbound_body', 'inbound_response', 'notification'])],
            'body' => ['required', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
