<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Inbound endpoint config — a stable, authenticated URL that receives and
 * validates external requests, then dispatches a configured action. The
 * runtime is served by InboundWebhookController → InboundRequestProcessor.
 *
 * The columns are listed because the model is `$guarded = []` with no
 * `$fillable`: without this, static analysis sees an untyped Model and every
 * `$endpoint->handle` in the codebase is an undefined property. They were
 * carried in the phpstan baseline instead, which meant each new use of a
 * perfectly ordinary column had to be waved through by hand.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $handle
 * @property string|null $description
 * @property bool $enabled
 * @property string $path
 * @property array<int,string>|null $allowed_methods
 * @property string $auth_type
 * @property array<string,mixed> $auth_config The accessor always answers with an
 *                                            array — the column is `text nullable` and holds ciphertext, and
 *                                            the mutator writes `null` for an empty set.
 * @property int|null $brand_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $expected_content_type
 * @property int $max_payload_kb
 * @property bool $replay_protection_enabled
 * @property array<string,mixed>|null $rate_limit_config
 * @property string $logging_mode
 * @property array<string,mixed>|null $mapping_config
 * @property string $action_type
 * @property array<string,mixed>|null $action_config
 * @property array<string,mixed>|null $response_config
 */
class InboundEndpoint extends Model
{
    use HasBrand;
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
                $endpoint->uuid = (string) Str::uuid();
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
