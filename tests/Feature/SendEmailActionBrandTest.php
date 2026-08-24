<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Sending\SenderIdentity;
use Goldnead\WebhookManager\Actions\SendEmailAction;
use Goldnead\WebhookManager\Contracts\SenderIdentityResolver;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Rules are brand-scoped; until 2026-08-24 the mail they sent was not.
 *
 * On a host serving several brands from one process, `Mail::raw()` meant one
 * brand's rule leaving through another brand's relay — rejected there, or worse,
 * accepted under the wrong identity.
 */
class SendEmailActionBrandTest extends TestCase
{
    protected function bindIdentity(SenderIdentity $identity): void
    {
        $this->app->bind(SenderIdentityResolver::class, fn () => new class($identity) implements SenderIdentityResolver
        {
            public function __construct(private SenderIdentity $identity) {}

            public function resolve(?int $brandId): SenderIdentity
            {
                return $this->identity;
            }
        });
    }

    /**
     * The From the message actually carries.
     *
     * Mail::fake() records mailables, not raw sends, so it cannot answer the
     * one question here. The MessageSending event can: it fires with the
     * assembled message, which is where the brand identity either won or did not.
     */
    protected function capturedFrom(): ?string
    {
        return $this->captured;
    }

    protected ?string $captured = null;

    protected function listenForFrom(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $from = $event->message->getFrom();
            $this->captured = $from === [] ? null : $from[0]->getAddress();
        });
    }

    protected function execute(array $config = []): ExecutionResult
    {
        $context = new ExecutionContext(new TriggerEvent('test', 'test', null, []));

        return (new SendEmailAction)->execute(array_merge([
            'to' => 'jemand@example.com',
            'subject' => 'Betreff',
            'body' => 'Text',
        ], $config), $context);
    }

    #[Test]
    public function a_brand_identity_wins_over_the_rule(): void
    {
        // No Mail::fake() here: it swallows the send, so MessageSending never
        // fires and the From — the only thing this test is about — is never
        // observable. Testbench's array mailer delivers nowhere and still fires
        // the event.
        $this->listenForFrom();

        $this->bindIdentity(SenderIdentity::of(null, 'marke@example.com', 'Marke', null));

        $result = $this->execute(['from' => 'die-rule@example.com']);

        $this->assertTrue($result->ok);

        // The rule may not impersonate a brand. Whoever has a declared identity
        // keeps it, whatever the rule author typed — that is the whole point.
        $this->assertSame('marke@example.com', $this->capturedFrom());
    }

    #[Test]
    public function without_a_brand_identity_the_rule_still_decides(): void
    {
        // fromConfig() carries no address, which is what a single-brand install
        // resolves to. The rule's own `from` must survive that — otherwise this
        // change would quietly break every existing rule.
        $this->listenForFrom();
        $this->bindIdentity(SenderIdentity::fromConfig());

        $this->assertTrue($this->execute(['from' => 'die-rule@example.com'])->ok);
        $this->assertSame('die-rule@example.com', $this->capturedFrom());
    }

    #[Test]
    public function a_refusing_identity_fails_the_action_instead_of_reporting_success(): void
    {
        Mail::fake();

        $this->bindIdentity(SenderIdentity::refusing('Brand declares no from_address.'));

        $result = $this->execute();

        // A rule that says "email sent" while nothing left the building is worse
        // than one that says it could not send.
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('refused', $result->message);

        Mail::assertNothingSent();
    }

    #[Test]
    public function it_still_validates_its_config_first(): void
    {
        $this->assertFalse($this->execute(['to' => null])->ok);
        $this->assertFalse($this->execute(['subject' => ''])->ok);
        $this->assertFalse($this->execute(['body' => ''])->ok);
    }
}
