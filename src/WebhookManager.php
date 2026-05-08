<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Goldnead\WebhookManager\Contracts\ConditionInterface;
use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;
use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\ConditionRegistry;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;

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
    ) {
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
