<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Illuminate\Support\Str;

class CreateRuleAction
{
    public function __invoke(array $attributes): Rule
    {
        $attributes = $this->normalize($attributes);

        $rule = new Rule();
        $rule->fill($attributes);
        $rule->save();

        return $rule->fresh();
    }

    protected function normalize(array $attributes): array
    {
        $attributes['handle'] = $attributes['handle']
            ?? Str::slug($attributes['name'] ?? Str::random(8));
        $attributes['enabled'] = (bool) ($attributes['enabled'] ?? true);
        $attributes['stop_on_failure'] = (bool) ($attributes['stop_on_failure'] ?? false);
        $attributes['order_index'] = (int) ($attributes['order_index'] ?? 0);
        $attributes['actions'] = $attributes['actions'] ?? [];

        return $attributes;
    }
}
