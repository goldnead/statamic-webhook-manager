<?php

namespace Goldnead\WebhookManager\Domain\Rule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub model for the Rule engine.
 *
 * TODO: REVIEW — Rule evaluation is currently a no-op (Rules\RuleEngine).
 * Schema is finalised so existing rules survive the iterative rollout.
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
