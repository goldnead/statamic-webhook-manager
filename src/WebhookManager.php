<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Goldnead\WebhookManager\Contracts\ConditionInterface;
use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Contracts\PresetInterface;
use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;
use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\ConditionRegistry;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Registries\PresetRegistry;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Triggers\CustomEventTrigger;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Public extension API. Resolved as `app('webhook-manager')` and reachable
 * via the WebhookManager facade.
 *
 * Example:
 *   use Goldnead\WebhookManager\Facades\WebhookManager;
 *   WebhookManager::registerTrigger(new MyCustomTrigger());
 */
class WebhookManager
{
    public function __construct(
        protected TriggerRegistry $triggers,
        protected ConditionRegistry $conditions,
        protected ActionRegistry $actions,
        protected AuthSchemeRegistry $authSchemes,
        protected VariableResolverRegistry $variableResolvers,
        protected SuccessEvaluatorRegistry $successEvaluators,
        protected InboundActionHandlerRegistry $inboundActionHandlers,
        protected PresetRegistry $presets,
        protected Dispatcher $events,
    ) {
    }

    /**
     * Turn ANY Laravel/Statamic event class into a webhook trigger.
     *
     * This funnels both the config-driven `webhook-manager.event_triggers`
     * entries and programmatic callers into one place:
     *
     *   1. Register a generic CustomEventTrigger in the TriggerRegistry so
     *      the event shows up in the CP trigger picker automatically.
     *   2. Attach ONE generic listener to the event class that normalises
     *      the raw event into a TriggerEvent and re-emits the existing
     *      TriggerDetected event — reusing the standard dispatch pipeline
     *      (TriggerDetected → DispatchTriggerListener → TriggerDispatcher).
     *
     * Example:
     *   WebhookManager::registerEventTrigger(\App\Events\OrderShipped::class, [
     *       'handle'      => 'order.shipped',
     *       'label'       => 'Order — shipped',
     *       'source_type' => 'order',
     *       'payload'     => fn ($event) => ['id' => $event->orderId],
     *   ]);
     *
     * @param  string  $eventClass  FQCN of the event to listen for.
     * @param  array{handle?:string,label?:string,source_type?:string,payload?:mixed,description?:string}  $config
     */
    public function registerEventTrigger(string $eventClass, array $config = []): self
    {
        $handle = $config['handle'] ?? $eventClass;

        $trigger = new CustomEventTrigger(
            handle: $handle,
            label: $config['label'] ?? $handle,
            sourceType: $config['source_type'] ?? 'event',
            payloadResolver: $this->normalisePayloadResolver($config['payload'] ?? null),
            description: $config['description'] ?? null,
        );

        $this->triggers->register($trigger);

        // ONE generic listener per configured event. It looks the trigger up
        // by handle at fire time (so re-registration under the same handle is
        // honoured) and re-emits the standard TriggerDetected event.
        $this->events->listen($eventClass, function ($event = null) use ($handle) {
            $trigger = $this->triggers->get($handle);
            if (! $trigger) {
                return;
            }

            // Laravel passes the event object for object events; for classic
            // string events the payload arrives as the first argument.
            $this->events->dispatch(new TriggerDetected($trigger->build($event)));
        });

        return $this;
    }

    /**
     * Coerce a configured payload mapper into a callable.
     *
     * Accepts: a Closure/callable, an invokable class-string, or a
     * [class, method] pair. Class-strings are resolved through the container.
     *
     * @return (callable(mixed): mixed)|null
     */
    protected function normalisePayloadResolver(mixed $payload): ?callable
    {
        if ($payload === null) {
            return null;
        }

        if ($payload instanceof \Closure) {
            return $payload;
        }

        // Invokable class-string, e.g. \App\Webhooks\OrderShippedPayload::class.
        if (is_string($payload) && class_exists($payload)) {
            $instance = app($payload);

            return fn (mixed $event) => $instance($event);
        }

        if (is_callable($payload)) {
            return $payload;
        }

        return null;
    }

    public function registerPreset(PresetInterface $preset): self
    {
        $this->presets->register($preset);
        return $this;
    }

    public function presets(): PresetRegistry
    {
        return $this->presets;
    }

    public function registerTrigger(TriggerInterface $trigger): self
    {
        $this->triggers->register($trigger);
        return $this;
    }

    public function registerCondition(ConditionInterface $condition): self
    {
        $this->conditions->register($condition);
        return $this;
    }

    public function registerAction(ActionInterface $action): self
    {
        $this->actions->register($action);
        return $this;
    }

    public function registerAuthScheme(AuthVerifierInterface $verifier): self
    {
        $this->authSchemes->register($verifier);
        return $this;
    }

    public function registerVariableResolver(TemplateVariableResolverInterface $resolver): self
    {
        $this->variableResolvers->register($resolver);
        return $this;
    }

    public function registerSuccessEvaluator(SuccessEvaluatorInterface $evaluator): self
    {
        $this->successEvaluators->register($evaluator);
        return $this;
    }

    public function registerInboundActionHandler(InboundActionHandlerInterface $handler): self
    {
        $this->inboundActionHandlers->register($handler);
        return $this;
    }

    public function triggers(): TriggerRegistry
    {
        return $this->triggers;
    }

    public function actions(): ActionRegistry
    {
        return $this->actions;
    }

    public function conditions(): ConditionRegistry
    {
        return $this->conditions;
    }

    public function authSchemes(): AuthSchemeRegistry
    {
        return $this->authSchemes;
    }

    public function variableResolvers(): VariableResolverRegistry
    {
        return $this->variableResolvers;
    }

    public function successEvaluators(): SuccessEvaluatorRegistry
    {
        return $this->successEvaluators;
    }

    public function inboundActionHandlers(): InboundActionHandlerRegistry
    {
        return $this->inboundActionHandlers;
    }
}
