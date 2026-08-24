<?php

namespace Goldnead\WebhookManager\Sending;

use Goldnead\BrandContext\Sending\BrandSenderIdentity as BrandContextIdentity;
use Goldnead\WebhookManager\Contracts\SenderIdentityResolver;

/**
 * The shipped resolver: whatever the current brand declares under
 * `settings.mail`, and the host's own configuration when no brand declares
 * anything. A single-brand installation sends exactly as it did before.
 */
class BrandSenderIdentity extends BrandContextIdentity implements SenderIdentityResolver {}
