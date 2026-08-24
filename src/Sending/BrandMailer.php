<?php

namespace Goldnead\WebhookManager\Sending;

use Goldnead\BrandContext\Sending\BrandMailer as BrandContextMailer;
use Goldnead\WebhookManager\Contracts\SenderIdentityResolver;

/**
 * The one door every brand-scoped mail in this package leaves through.
 *
 * The mechanism is {@see BrandContextMailer}: values on the message, never
 * state in the config, a refusal as a return value rather than an exception.
 * This subclass only narrows which resolver gets injected.
 *
 * Not everything mailed here goes through it. A delivery-failure alert is a
 * message *from the host about* a brand, not a message from the brand, and it
 * must arrive even when the brand's own relay is the thing that broke — so it
 * keeps the host mailer. Same call automations made for its FailureAlerter.
 */
class BrandMailer extends BrandContextMailer
{
    public function __construct(SenderIdentityResolver $identities)
    {
        parent::__construct($identities);
    }
}
