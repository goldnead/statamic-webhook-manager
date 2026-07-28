<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for creating/updating an inbound endpoint
 * (path, auth scheme, payload limits, mapping and action config).
 */
class SaveInboundEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $endpointId = $this->route('webhookInbound')?->id ?? null;

        // Per-brand, matching `webhook_inbounds_brand_id_handle_unique`. See
        // SaveOutboundWebhookRequest for why the brand cannot be left implicit.
        $brandId = app('brand-context')->currentId();

        return [
            'name' => ['required', 'string', 'max:120'],
            'handle' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('webhook_inbounds', 'handle')
                    ->where('brand_id', $brandId)
                    ->ignore($endpointId),
            ],
            'enabled' => ['boolean'],
            'path' => ['required', 'string', 'max:255'],
            'allowed_methods' => ['array'],
            'allowed_methods.*' => [Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'auth_type' => ['required', Rule::in(['none', 'static_header', 'bearer', 'basic', 'hmac', 'ip_allowlist'])],
            'auth_config' => ['nullable', 'array'],
            // Same trap as on the outbound side: InboundController persists
            // `$request->validated()`, so an unlisted `auth_config_json` is
            // discarded and the endpoint ends up with an auth scheme but no
            // credentials — which the verifiers answer with a blanket reject.
            'auth_config_json' => ['nullable', 'string'],
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
