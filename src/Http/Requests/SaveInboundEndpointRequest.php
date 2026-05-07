<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TODO: REVIEW — inbound endpoints are wired through but the runtime
 * controller still returns 501. Validation rules are in place so a
 * future iteration can save endpoints and turn on the flow with a flip.
 */
class SaveInboundEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $endpointId = $this->route('endpoint')?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_inbounds', 'handle')->ignore($endpointId),
            ],
            'enabled' => ['boolean'],
            'path' => ['required', 'string', 'max:255'],
            'allowed_methods' => ['array'],
            'allowed_methods.*' => [Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'auth_type' => ['required', Rule::in(['none', 'static_header', 'bearer', 'basic', 'hmac', 'ip_allowlist'])],
            'auth_config' => ['nullable', 'array'],
            'expected_content_type' => ['nullable', 'string', 'max:120'],
            'max_payload_kb' => ['integer', 'min:1', 'max:65536'],
            'replay_protection_enabled' => ['boolean'],
            'rate_limit_config' => ['nullable', 'array'],
            'logging_mode' => ['nullable', Rule::in(['full', 'partial', 'none'])],
            'mapping_config' => ['nullable', 'array'],
            'action_type' => ['nullable', 'string'],
            'action_config' => ['nullable', 'array'],
            'response_config' => ['nullable', 'array'],
        ];
    }
}
