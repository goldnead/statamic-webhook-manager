<?php

namespace Goldnead\WebhookManager\Domain\Log\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     $id
 * @property string  $uuid
 * @property string  $level
 * @property string  $type
 * @property ?int    $related_webhook_id
 * @property ?int    $related_endpoint_id
 * @property ?int    $related_delivery_id
 * @property ?string $correlation_id
 * @property string  $message
 * @property array   $context
 */
class LogEntry extends Model
{
    use HasBrand;
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'webhook_logs';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (LogEntry $log) {
            if (! $log->uuid) {
                $log->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
