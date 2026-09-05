<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Concerns;

/**
 * One wording and one colour for a delivery's error type, wherever it is shown.
 *
 * The overview used to print the raw database enum (`network`, `auth`) with a
 * hard-coded red badge, while the delivery listing and the detail page one
 * click away called the same field "Authentifizierungsfehler" in the colour the
 * type deserves. Same data, three answers.
 */
trait PresentsDeliveryErrors
{
    protected function errorTypeColor(?string $type): string
    {
        return match ($type) {
            'network' => 'orange',
            'timeout' => 'amber',
            'auth' => 'red',
            'client' => 'yellow',
            'server' => 'red',
            'payload' => 'purple',
            'configuration' => 'blue',
            'internal' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Falls back to the raw handle when a type has no translation — never to a
     * blank, and never to "Unknown", which would hide which error it was.
     */
    protected function errorTypeLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $key = 'webhook-manager::messages.failure_types.'.$type;
        $translated = __($key);

        return $translated === $key ? $type : $translated;
    }
}
