<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int    $id
 * @property string $uuid
 * @property string $name
 * @property string $handle
 * @property bool   $enabled
 * @property string $trigger_type
 * @property array  $trigger_config
 * @property string $url
 * @property string $method
 * @property array  $headers
 * @property int    $timeout_seconds
 * @property bool   $follow_redirects
 * @property string $auth_type
 * @property array  $auth_config       decrypted auth config
 * @property string $payload_type
 * @property string $payload_template
 * @property array  $conditions
 * @property array  $retry_strategy
 * @property bool   $queue_enabled
 * @property string $log_body_mode
 */
class OutboundWebhook extends Model
{
    use HasBrand;
    use HasFactory;

    protected $table = 'webhook_outbounds';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'trigger_config' => 'array',
        'headers' => 'array',
        'follow_redirects' => 'boolean',
        'conditions' => 'array',
        'retry_strategy' => 'array',
        'queue_enabled' => 'boolean',
        'idempotency_enabled' => 'boolean',
        'success_matcher' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutboundWebhook $hook) {
            if (! $hook->uuid) {
                $hook->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'outbound_webhook_id');
    }

    /**
     * Auth config is encrypted at rest. Reads/writes go through Crypt
     * so the database never holds plaintext secrets.
     */
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
                if (empty($value)) {
                    return null;
                }
                return Crypt::encrypt($value);
            },
        );
    }

    public function triggerHandle(): string
    {
        return $this->trigger_type;
    }

    public function isQueueEnabled(): bool
    {
        return (bool) ($this->queue_enabled ?? true);
    }
}
