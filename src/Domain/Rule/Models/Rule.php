<?php

namespace Goldnead\WebhookManager\Domain\Rule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A "When → If → Then" automation rule: a trigger, a condition tree and an
 * ordered action list. Evaluated and executed by Rules\RuleEngine.
 */
class Rule extends Model
{
    use HasFactory;

    protected $table = 'webhook_rules';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'trigger_config' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'stop_on_failure' => 'boolean',
        'order_index' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Rule $rule) {
            if (! $rule->uuid) {
                $rule->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
