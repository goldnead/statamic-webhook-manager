<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent record of one outbound delivery attempt.
 *
 * Holds full request/response snapshots so failures can be diagnosed
 * and replayed without re-executing upstream logic.
 *
 * @property int     $id
 * @property string  $uuid
 * @property int     $outbound_webhook_id
 * @property ?int    $rule_id
 * @property string  $trigger_type
 * @property string  $status
 * @property string  $request_url
 * @property string  $request_method
 * @property array   $request_headers
 * @property string  $request_body
 * @property ?int    $response_status
 * @property ?array  $response_headers
 * @property ?string $response_body
 * @property ?string $error_type
 * @property ?string $error_message
 * @property int     $attempts
 * @property ?\Carbon\Carbon $first_attempted_at
 * @property ?\Carbon\Carbon $last_attempted_at
 * @property ?\Carbon\Carbon $next_retry_at
 * @property ?string $correlation_id
 * @property ?string $idempotency_key
 * @property ?int    $duration_ms
 * @property bool    $rendered_from_snapshot
 */
class Delivery extends Model
{
    use HasBrand;
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'webhook_deliveries';

    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'response_status' => 'integer',
        'first_attempted_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'rendered_from_snapshot' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Delivery $d) {
            if (! $d->uuid) {
                $d->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function outboundWebhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class, 'outbound_webhook_id');
    }

    public function isReplayable(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_FAILED => 'failed',
            self::STATUS_PROCESSING => 'processing',
            self::STATUS_CANCELLED => 'cancelled',
            default => 'pending',
        };
    }
}
