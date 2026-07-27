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
            // The CP edit screen submits the auth config as a JSON string in
            // `auth_config_json` (write-only — the stored secret is never sent
            // back to the browser). It MUST be listed here: the controllers
            // persist `$request->validated()`, so an unvalidated key is
            // silently dropped and the secret never reaches the database,
            // leaving `auth_type=hmac` configured but unsigned on the wire.
            'auth_config_json' => ['nullable', 'string'],
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
            $this->validateAuthConfig($v);
        });

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

    /**
     * Guard the auth config so a hook can never claim an auth scheme it has
     * no credentials for.
     *
     * Two failure modes, both previously silent:
     *   - unparsable JSON was discarded without a word, and
     *   - `auth_type` could be set to `hmac` with no secret at all, which
     *     produced an *unsigned* request while the UI reported "HMAC
     *     signature".
     */
    protected function validateAuthConfig($validator): void
    {
        $authType = (string) $this->input('auth_type', 'none');
        $raw = trim((string) $this->input('auth_config_json', ''));

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $validator->errors()->add(
                    'auth_config_json',
                    __('webhook-manager::messages.auth_config_invalid_json'),
                );

                return;
            }
            if ($authType === 'hmac' && trim((string) ($decoded['secret'] ?? '')) === '') {
                $validator->errors()->add(
                    'auth_config_json',
                    __('webhook-manager::messages.auth_config_hmac_secret_required'),
                );
            }

            return;
        }

        // Blank field: fine when a secret is already stored (that's how the
        // edit screen says "keep what you have"), or when an explicit
        // auth_config array was posted by an API client. Otherwise the hook
        // would go out unauthenticated under a scheme that promises otherwise.
        if ($authType === 'none') {
            return;
        }
        if (is_array($this->input('auth_config')) && $this->input('auth_config') !== []) {
            return;
        }
        if (! empty($this->route('webhook')?->auth_config)) {
            return;
        }

        $validator->errors()->add(
            'auth_config_json',
            __('webhook-manager::messages.auth_config_required'),
        );
    }
}
