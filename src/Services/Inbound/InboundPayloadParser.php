<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Illuminate\Http\Request;

class InboundPayloadParser
{
    /**
     * @return array{ok:bool, data:array, error?:string}
     */
    public function parse(Request $request, string $expectedContentType): array
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getContent();
            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['ok' => false, 'data' => [], 'error' => 'Invalid JSON: '.json_last_error_msg()];
            }
            return ['ok' => true, 'data' => is_array($decoded) ? $decoded : ['value' => $decoded]];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return ['ok' => true, 'data' => $request->all()];
        }

        return [
            'ok' => false,
            'data' => [],
            'error' => "Unsupported Content-Type: {$contentType}",
        ];
    }
}
