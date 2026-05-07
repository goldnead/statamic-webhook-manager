<?php

namespace Goldnead\WebhookManager\Services\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Thin wrapper around Laravel's HTTP client. Centralises timeouts,
 * redirects, SSL options and exception normalisation so DeliveryEngine
 * can stay framework-agnostic-ish.
 */
class HttpClient
{
    public function __construct(protected HttpFactory $http)
    {
    }

    /**
     * @return array{ok:bool, status:?int, headers:array, body:?string, duration_ms:int, error_message:?string, error_kind:?string}
     */
    public function send(array $request, int $timeoutSeconds = 15, bool $followRedirects = true): array
    {
        $start = microtime(true);

        try {
            $client = $this->http
                ->timeout($timeoutSeconds)
                ->connectTimeout((int) config('webhook-manager.http.connect_timeout_seconds', 5))
                ->withHeaders($request['headers'] ?? [])
                ->withOptions([
                    'allow_redirects' => $followRedirects,
                    'verify' => (bool) config('webhook-manager.http.verify_ssl', true),
                    'http_errors' => false,
                ]);

            $method = strtoupper($request['method'] ?? 'POST');
            $url = (string) $request['url'];
            $body = (string) ($request['body'] ?? '');

            /** @var Response $response */
            $response = match ($method) {
                'GET', 'DELETE' => $client->send($method, $url),
                default => $client->withBody($body, $request['headers']['Content-Type'] ?? 'application/json')
                    ->send($method, $url),
            };

            return [
                'ok' => true,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'error_message' => null,
                'error_kind' => null,
            ];
        } catch (ConnectionException $e) {
            return $this->error('network', $e->getMessage(), $start);
        } catch (\Throwable $e) {
            return $this->error('internal', $e->getMessage(), $start);
        }
    }

    /** @return array{ok:bool, status:?int, headers:array, body:?string, duration_ms:int, error_message:string, error_kind:string} */
    protected function error(string $kind, string $message, float $start): array
    {
        return [
            'ok' => false,
            'status' => null,
            'headers' => [],
            'body' => null,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'error_message' => $message,
            'error_kind' => $kind,
        ];
    }
}
