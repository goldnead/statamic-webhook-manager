<?php

namespace Goldnead\WebhookManager\Facades;

use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\ConditionRegistry;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\WebhookManager as WebhookManagerService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static WebhookManagerService registerTrigger(\Goldnead\WebhookManager\Contracts\TriggerInterface $trigger)
 * @method static WebhookManagerService registerCondition(\Goldnead\WebhookManager\Contracts\ConditionInterface $condition)
 * @method static WebhookManagerService registerAction(\Goldnead\WebhookManager\Contracts\ActionInterface $action)
 * @method static WebhookManagerService registerAuthScheme(\Goldnead\WebhookManager\Contracts\AuthVerifierInterface $verifier)
 * @method static WebhookManagerService registerVariableResolver(\Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface $resolver)
 * @method static WebhookManagerService registerSuccessEvaluator(\Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface $evaluator)
 * @method static WebhookManagerService registerPreset(\Goldnead\WebhookManager\Contracts\PresetInterface $preset)
 * @method static WebhookManagerService registerInboundActionHandler(\Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface $handler)
 * @method static \Goldnead\WebhookManager\Registries\PresetRegistry presets()
 * @method static \Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry inboundActionHandlers()
 * @method static TriggerRegistry triggers()
 * @method static ConditionRegistry conditions()
 * @method static ActionRegistry actions()
 * @method static AuthSchemeRegistry authSchemes()
 * @method static VariableResolverRegistry variableResolvers()
 * @method static SuccessEvaluatorRegistry successEvaluators()
 */
class WebhookManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'webhook-manager';
    }
}
