<?php

namespace Goldnead\WebhookManager\Domain\Template\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    public const TYPE_OUTBOUND_BODY = 'outbound_body';
    public const TYPE_INBOUND_RESPONSE = 'inbound_response';
    public const TYPE_NOTIFICATION = 'notification';

    protected $table = 'webhook_templates';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Template $tpl) {
            if (! $tpl->uuid) {
                $tpl->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
