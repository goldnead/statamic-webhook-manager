<?php

namespace Goldnead\WebhookManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, \Goldnead\WebhookManager\Domain\Delivery\Models\Delivery> forSubject(string $type, int|string $id, int $limit = 50)
 * @method static int countForSubject(string $type, int|string $id)
 * @method static list<string> subjectTypes()
 *
 * @see \Goldnead\WebhookManager\WebhookLog
 */
class WebhookLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'webhook-manager.log';
    }
}
