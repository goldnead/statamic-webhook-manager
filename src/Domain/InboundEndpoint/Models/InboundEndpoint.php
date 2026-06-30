<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Inbound endpoint config — a stable, authenticated URL that receives and
 * validates external requests, then dispatches a configured action. The
 * runtime is served by InboundWebhookController → InboundRequestProcessor.
 */
class InboundEndpoint extends Model
{
    use HasFactory;

    protected $table = 'webhook_inbounds';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'allowed_methods' => 'array',
        'rate_limit_config' => 'array',
        'mapping_config' => 'array',
        'action_config' => 'array',
        'response_config' => 'array',
        'replay_protection_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (InboundEndpoint $endpoint) {
            if (! $endpoint->uuid) {
                $endpoint->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected function authConfig(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return [];
                }
                try {
                    return Crypt::decrypt($value);
                } catch (\Throwable $e) {
                    return [];
                }
            },
            set: function ($value) {
                return empty($value) ? null : Crypt::encrypt($value);
            },
        );
    }
}
