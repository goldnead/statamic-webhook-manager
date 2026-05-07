<?php

namespace Goldnead\WebhookManager\Services\Http;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Repositories\TemplateRepository;
use Goldnead\WebhookManager\Templates\TemplateRenderer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Illuminate\Support\Str;

/**
 * Build a request snapshot (url/method/headers/body) from an outbound
 * webhook config + execution context. Auth headers and signatures are
 * applied here so the snapshot is the exact bytes that go on the wire.
 */
class HttpRequestFactory
{
    public function __construct(
        protected TemplateRenderer $renderer,
        protected AuthSchemeRegistry $authSchemes,
        protected TemplateRepository $templates,
    ) {
    }

    /**
     * @return array{method:string, url:string, headers:array<string,string>, body:string, idempotency_key:?string}
     */
    public function build(OutboundWebhook $hook, ExecutionContext $context): array
    {
        $body = $this->buildBody($hook, $context);

        $headers = (array) ($hook->headers ?? []);
        $contentType = $headers['Content-Type'] ?? null;
        if (! $contentType) {
            $headers['Content-Type'] = $hook->payload_type === 'form'
                ? 'application/x-www-form-urlencoded'
                : 'application/json';
        }
        $headers['Accept'] = $headers['Accept'] ?? 'application/json';
        $headers['User-Agent'] = $headers['User-Agent']
            ?? config('webhook-manager.http.user_agent', 'Statamic-Webhook-Manager/1.0');
        $headers['X-Webhook-Correlation'] = $context->event->correlationId;
        $headers['X-Webhook-Trigger'] = $context->event->triggerHandle;

        $request = [
            'method' => strtoupper($hook->method ?: 'POST'),
            'url' => $hook->url,
            'headers' => $headers,
            'body' => $body,
        ];

        $verifier = $this->authSchemes->get($hook->auth_type ?: 'none');
        if ($verifier) {
            $request = $verifier->sign($request, $hook->auth_config ?? []);
        }

        return $request + [
            'idempotency_key' => $hook->idempotency_enabled
                ? $this->idempotencyKey($hook, $context)
                : null,
        ];
    }

    /**
     * Resolve the template body in this priority order:
     *
     *   1. Library template referenced by `payload_template_handle`
     *   2. Inline `payload_template` text
     *   3. JSON-encoded TriggerEvent as a sensible default
     *
     * The library template wins if both are set so an operator can
     * "promote" an inline body to a library entry without having to
     * also clear the inline field on every hook.
     */
    protected function buildBody(OutboundWebhook $hook, ExecutionContext $context): string
    {
        $libraryHandle = (string) ($hook->payload_template_handle ?? '');
        if ($libraryHandle !== '') {
            $template = $this->templates->findByHandle($libraryHandle);
            if ($template && (string) $template->body !== '') {
                return $this->renderer->render((string) $template->body, $context);
            }
            // TODO: REVIEW — silently falling back to the inline body when
            // a referenced library template is missing keeps deliveries
            // alive but hides the misconfiguration. Consider classifying
            // this as a configuration failure once a centralised observer
            // exists.
        }

        $inline = (string) ($hook->payload_template ?? '');
        if ($inline === '') {
            // sensible default: JSON-encoded payload from the trigger
            return (string) json_encode($context->event->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $this->renderer->render($inline, $context);
    }

    protected function idempotencyKey(OutboundWebhook $hook, ExecutionContext $context): string
    {
        return sha1(implode('|', [
            $hook->id,
            $context->event->triggerHandle,
            $context->event->sourceReference ?? '',
            (string) $context->event->eventAt?->getTimestamp(),
        ]));
    }

    /**
     * Mask sensitive headers / payload keys for storage on a Delivery
     * snapshot, respecting the hook's logging mode.
     */
    public function maskedSnapshot(array $request, OutboundWebhook $hook, Delivery $delivery): void
    {
        $logMode = $hook->log_body_mode ?? config('webhook-manager.logging.mode', 'partial');

        $maskedHeaders = SecretMasker::maskHeaders(
            $request['headers'] ?? [],
            (array) config('webhook-manager.logging.mask_headers', []),
        );

        $delivery->request_headers = $maskedHeaders;
        $delivery->request_method = $request['method'];
        $delivery->request_url = $request['url'];

        $delivery->request_body = match ($logMode) {
            'none' => null,
            'partial' => Str::limit((string) ($request['body'] ?? ''), (int) config('webhook-manager.logging.partial_bytes', 4096), '… [truncated]'),
            default => (string) ($request['body'] ?? ''),
        };

        if ($key = $request['idempotency_key'] ?? null) {
            $delivery->idempotency_key = $key;
        }
    }
}
