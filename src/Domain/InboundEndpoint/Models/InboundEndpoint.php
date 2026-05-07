<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Inbound endpoint config.
 *
 * TODO: REVIEW — controller currently returns 501; this model is in place
 * so persistence and CP wiring can be developed iteratively without
 * breaking schema later.
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
