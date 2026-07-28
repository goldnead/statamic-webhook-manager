<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule as AutomationRule;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Http\Requests\SaveInboundEndpointRequest;
use Goldnead\WebhookManager\Http\Requests\SaveOutboundWebhookRequest;
use Goldnead\WebhookManager\Http\Requests\SaveRuleRequest;
use Goldnead\WebhookManager\Http\Requests\SaveTemplateRequest;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The schema and the validator have to agree about what "unique" means.
 *
 * Since v1.5.0 the database makes a handle unique per brand
 * (`webhook_*_brand_id_handle_unique`), but the CP form requests still asked
 * `Rule::unique('webhook_outbounds', 'handle')` — a raw query-builder check
 * that no Eloquent scope ever touches, and therefore global. One brand claiming
 * a handle blocked it for every other brand, and said so out loud: "The handle
 * has already been taken" is an answer about rows the asking tenant is not
 * allowed to see.
 *
 * That is the same class of defect as an over-permissive unique, read from the
 * other side: a constraint whose name promises one scope and whose enforcement
 * has another.
 */
class HandleUniquenessIsPerBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        app('brand-context')->forget();
    }

    private function makeBrand(string $handle): int
    {
        return (int) DB::table('brands')->insertGetId([
            'handle' => $handle,
            'name' => ucfirst($handle),
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Runs a form request's rules against a payload, the way the CP would. */
    private function validate(string $requestClass, array $payload): \Illuminate\Validation\Validator
    {
        /** @var FormRequest $request */
        $request = $requestClass::create('/', 'POST', $payload);
        $request->setContainer($this->app);

        return Validator::make($payload, $request->rules());
    }

    public function test_another_brand_may_claim_a_handle_that_is_taken_elsewhere(): void
    {
        $brandA = $this->makeBrand('brand-a');
        $brandB = $this->makeBrand('brand-b');

        BrandContext::runFor($brandA, function () {
            OutboundWebhook::create([
                'name' => 'A hook',
                'handle' => 'order-created',
                'trigger_type' => 'entry.published',
                'url' => 'https://a.example.test/hook',
            ]);
        });

        $payload = [
            'name' => 'B hook',
            'handle' => 'order-created',
            'trigger_type' => 'entry.published',
            'url' => 'https://b.example.test/hook',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
        ];

        $errors = BrandContext::runFor(
            $brandB,
            fn () => $this->validate(SaveOutboundWebhookRequest::class, $payload)->errors()
        );

        $this->assertFalse(
            $errors->has('handle'),
            'Brand B was refused a handle that only brand A uses: '.$errors->first('handle')
        );
    }

    public function test_the_same_brand_is_still_refused_a_handle_it_already_uses(): void
    {
        // The point of scoping the check is not to loosen it. Inside one brand
        // the handle stays exactly as unique as it was.
        $brandA = $this->makeBrand('brand-a');

        $payload = BrandContext::runFor($brandA, function () {
            OutboundWebhook::create([
                'name' => 'A hook',
                'handle' => 'order-created',
                'trigger_type' => 'entry.published',
                'url' => 'https://a.example.test/hook',
            ]);

            return [
                'name' => 'A second hook',
                'handle' => 'order-created',
                'trigger_type' => 'entry.published',
                'url' => 'https://a.example.test/other',
                'auth_type' => 'none',
                'payload_type' => 'raw_json',
            ];
        });

        $errors = BrandContext::runFor(
            $brandA,
            fn () => $this->validate(SaveOutboundWebhookRequest::class, $payload)->errors()
        );

        $this->assertTrue($errors->has('handle'), 'A handle was allowed twice inside one brand.');
    }

    public function test_inbound_rule_and_template_handles_are_scoped_the_same_way(): void
    {
        $brandA = $this->makeBrand('brand-a');
        $brandB = $this->makeBrand('brand-b');

        BrandContext::runFor($brandA, function () {
            InboundEndpoint::create(['name' => 'A', 'handle' => 'shared', 'path' => 'a']);
            AutomationRule::create(['name' => 'A', 'handle' => 'shared', 'trigger_type' => 'entry.published']);
            Template::create(['name' => 'A', 'handle' => 'shared', 'type' => 'outbound_body', 'body' => '{}']);
        });

        $cases = [
            SaveInboundEndpointRequest::class => [
                'name' => 'B', 'handle' => 'shared', 'path' => 'b', 'auth_type' => 'none',
            ],
            SaveRuleRequest::class => [
                'name' => 'B', 'handle' => 'shared', 'trigger_type' => 'entry.published',
            ],
            SaveTemplateRequest::class => [
                'name' => 'B', 'handle' => 'shared', 'type' => 'outbound_body', 'body' => '{}',
            ],
        ];

        foreach ($cases as $requestClass => $payload) {
            $errors = BrandContext::runFor(
                $brandB,
                fn () => $this->validate($requestClass, $payload)->errors()
            );

            $this->assertFalse(
                $errors->has('handle'),
                $requestClass.' refused brand B a handle only brand A uses: '.$errors->first('handle')
            );
        }
    }

    public function test_a_webhook_cannot_borrow_a_template_belonging_to_another_brand(): void
    {
        // The mirror image: `Rule::exists` was global too, so brand B could
        // point a webhook at brand A's template handle and pass validation.
        // At render time the brand-scoped model finds nothing and the body
        // silently falls back — a reference that validates and never resolves.
        $brandA = $this->makeBrand('brand-a');
        $brandB = $this->makeBrand('brand-b');

        BrandContext::runFor($brandA, function () {
            Template::create([
                'name' => 'A body', 'handle' => 'a-only', 'type' => 'outbound_body', 'body' => '{}',
            ]);
        });

        $payload = [
            'name' => 'B hook',
            'handle' => 'b-hook',
            'trigger_type' => 'entry.published',
            'url' => 'https://b.example.test/hook',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template_handle' => 'a-only',
        ];

        $errors = BrandContext::runFor(
            $brandB,
            fn () => $this->validate(SaveOutboundWebhookRequest::class, $payload)->errors()
        );

        $this->assertTrue(
            $errors->has('payload_template_handle'),
            'Brand B was allowed to reference a template that belongs to brand A.'
        );
    }
}
