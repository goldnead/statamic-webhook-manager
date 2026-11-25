<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Scopes\BrandScope;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * `ResolveInboundBrand` writes two log lines before it has set a current brand.
 * brand-context refuses a brand-less `HasBrand` write in multi-brand mode, so
 * the lines are written explicitly under the default brand: same row as before,
 * and no 500 for a misaddressed delivery. Holds with every brand-context
 * release, which is why this asserts the brand and not the exception.
 */
class InboundBrandLogRowsBelongToDefaultBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        app('brand-context')->forget();
    }

    public function test_the_unknown_brand_line_is_written_under_the_default_brand_and_leaves_no_current_brand(): void
    {
        $default = (int) DB::table('brands')->where('is_default', true)->value('id');

        $this->postJson('/webhooks/inbound/gibt-es-nicht/events', ['a' => 1])->assertStatus(404);

        $this->assertSame($default, (int) LogEntry::withoutGlobalScope(BrandScope::class)
            ->where('type', 'inbound_brand_not_found')->value('brand_id'));
        $this->assertFalse(app('brand-context')->hasCurrent());
    }

    public function test_the_defaulted_line_is_written_under_the_default_brand_and_leaves_no_current_brand(): void
    {
        $default = (int) DB::table('brands')->where('is_default', true)->value('id');

        $this->postJson('/webhooks/inbound/kein-endpunkt', ['a' => 1])->assertStatus(404);

        $this->assertSame($default, (int) LogEntry::withoutGlobalScope(BrandScope::class)
            ->where('type', 'inbound_brand_defaulted')->value('brand_id'));
        $this->assertFalse(app('brand-context')->hasCurrent());
    }
}
