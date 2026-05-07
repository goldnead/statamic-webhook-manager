<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\Templates\Resolvers\AssetVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\EntryVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\FormSubmissionVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\PayloadVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\SiteVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\SystemVariableResolver;
use Goldnead\WebhookManager\Templates\Resolvers\UserVariableResolver;

class VariableResolverRegistry
{
    /** @var array<string, TemplateVariableResolverInterface> */
    protected array $resolvers = [];

    public function register(TemplateVariableResolverInterface $resolver): void
    {
        $this->resolvers[$resolver->namespace()] = $resolver;
    }

    public function get(string $namespace): ?TemplateVariableResolverInterface
    {
        return $this->resolvers[$namespace] ?? null;
    }

    /** @return array<string, TemplateVariableResolverInterface> */
    public function all(): array
    {
        return $this->resolvers;
    }

    public function registerDefaults(): void
    {
        $this->register(new EntryVariableResolver());
        $this->register(new FormSubmissionVariableResolver());
        $this->register(new UserVariableResolver());
        $this->register(new AssetVariableResolver());
        $this->register(new SiteVariableResolver());
        $this->register(new SystemVariableResolver());
        $this->register(new PayloadVariableResolver());
    }
}
