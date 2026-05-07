<?php

namespace Goldnead\WebhookManager\Tests\Unit\Services;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\FailureClassifier;
use Goldnead\WebhookManager\Services\RetryPlanner;
use Goldnead\WebhookManager\Tests\TestCase;

class RetryPlannerTest extends TestCase
{
    public function test_does_not_retry_when_strategy_is_none(): void
    {
        $delivery = new Delivery(['attempts' => 1]);
        $hook = new OutboundWebhook(['retry_strategy' => ['strategy' => 'none', 'max_attempts' => 3]]);

        $planner = new RetryPlanner(new FailureClassifier());
        $next = $planner->plan($delivery, $hook, ['ok' => true, 'status' => 500]);
        $this->assertNull($next);
    }

    public function test_does_not_retry_after_max_attempts(): void
    {
        $delivery = new Delivery(['attempts' => 3]);
        $hook = new OutboundWebhook(['retry_strategy' => ['strategy' => 'exponential', 'max_attempts' => 3, 'retry_on_status' => [500]]]);

        $planner = new RetryPlanner(new FailureClassifier());
        $next = $planner->plan($delivery, $hook, ['ok' => true, 'status' => 500]);
        $this->assertNull($next);
    }

    public function test_schedules_exponential_retry_on_500(): void
    {
        $delivery = new Delivery(['attempts' => 1]);
        $hook = new OutboundWebhook([
            'retry_strategy' => [
                'strategy' => 'exponential',
                'max_attempts' => 3,
                'base_delay_seconds' => 30,
                'max_delay_seconds' => 3600,
                'retry_on_status' => [500],
                'retry_on_network_errors' => true,
            ],
        ]);

        $planner = new RetryPlanner(new FailureClassifier());
        $next = $planner->plan($delivery, $hook, ['ok' => true, 'status' => 500]);
        $this->assertNotNull($next);
        $this->assertGreaterThan(time(), $next->getTimestamp());
    }

    public function test_does_not_retry_on_4xx_unless_in_allowlist(): void
    {
        $delivery = new Delivery(['attempts' => 1]);
        $hook = new OutboundWebhook(['retry_strategy' => [
            'strategy' => 'exponential',
            'max_attempts' => 3,
            'retry_on_status' => [500],
        ]]);

        $planner = new RetryPlanner(new FailureClassifier());
        $next = $planner->plan($delivery, $hook, ['ok' => true, 'status' => 422]);
        $this->assertNull($next);
    }
}
