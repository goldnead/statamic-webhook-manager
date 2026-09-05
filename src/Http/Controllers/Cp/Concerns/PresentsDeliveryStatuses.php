<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Concerns;

/**
 * One wording and one colour for a delivery's status, wherever it is shown.
 *
 * Sibling of PresentsDeliveryErrors, and it exists for the same reason one
 * field further along: the listing printed the raw database enum (`failed`,
 * `success`) while the detail page one click away said "Fehlgeschlagen". Same
 * record, two vocabularies, and the listing's version was not even German.
 */
trait PresentsDeliveryStatuses
{
    protected function statusColor(?string $status): string
    {
        return match ($status) {
            'success' => 'green',
            'failed' => 'red',
            'pending' => 'amber',
            'processing' => 'blue',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Falls back to the raw handle when a status has no translation — never to
     * a blank, and never to "Unknown", which would hide what the record says.
     */
    protected function statusLabel(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $key = 'webhook-manager::messages.cp.delivery_status.'.$status;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $status;
    }
}
