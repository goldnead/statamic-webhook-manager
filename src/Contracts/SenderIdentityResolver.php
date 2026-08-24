<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\BrandContext\Contracts\SenderIdentityResolver as BrandContextResolver;

/**
 * An empty sub-interface, on purpose.
 *
 * The contract itself lives in statamic-brand-context, where four addons agreed
 * on it in August 2026 rather than keeping a copy each. What this one adds is a
 * name a host can rebind for *this* package alone: swapping the resolver for
 * webhook-manager must not silently swap it for marketing as well.
 */
interface SenderIdentityResolver extends BrandContextResolver {}
