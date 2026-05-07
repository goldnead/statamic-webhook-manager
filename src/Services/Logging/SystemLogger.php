<?php

namespace Goldnead\WebhookManager\Services\Logging;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;

class SystemLogger
{
    public function info(string $type, string $message, array $context = []): LogEntry
    {
        return $this->write('info', $type, $message, $context);
    }

    public function warning(string $type, string $message, array $context = []): LogEntry
    {
        return $this->write('warning', $type, $message, $context);
    }

    public function error(string $type, string $message, array $context = []): LogEntry
    {
        return $this->write('error', $type, $message, $context);
    }

    public function debug(string $type, string $message, array $context = []): LogEntry
    {
        return $this->write('debug', $type, $message, $context);
    }

    protected function write(string $level, string $type, string $message, array $context): LogEntry
    {
        return LogEntry::create([
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'related_webhook_id' => $context['webhook_id'] ?? null,
            'related_endpoint_id' => $context['endpoint_id'] ?? null,
            'related_delivery_id' => $context['delivery_id'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'created_at' => now(),
        ]);
    }
}
