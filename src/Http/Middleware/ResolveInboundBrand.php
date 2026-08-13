<?php

namespace Goldnead\WebhookManager\Http\Middleware;

use Closure;
use Goldnead\BrandContext\Models\Brand;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts a brand on the request before the inbound endpoint is looked up.
 *
 * ## Why this exists
 *
 * `InboundEndpoint` is brand-scoped (`HasBrand`), and under multi-brand the
 * global scope fails closed: no current brand means no rows. Every other route
 * in this family gets its brand from something the caller carries — a CP
 * session, a bearer token, a link token. An inbound webhook carries none of
 * those. It is fired by Scaleway, Stripe, GitHub or n8n, which know a URL and
 * nothing else about the installation.
 *
 * So before this middleware existed, a multi-brand install answered **every**
 * inbound delivery with 404 "Endpoint not found or disabled" — the route
 * matched, the controller ran, and the lookup returned null because the scope
 * had already excluded every row. The endpoint was there. Nothing could see it.
 *
 * The brand therefore has to come out of the URL, which is the only thing the
 * sender holds.
 *
 * ## The two URL shapes
 *
 *     {prefix}/{brand}/{handle}   canonical under multi-brand
 *     {prefix}/{handle}           single-brand, and the pre-brand default
 *
 * The brand-qualified form names the brand explicitly and is what the CP
 * prints, on every install. An unknown brand handle is a 404 — the same answer
 * an unknown endpoint handle gets, so the URL cannot be used to enumerate which
 * brands exist.
 *
 * The short form resolves to the **default** brand under multi-brand, and to
 * the only brand there is under single-brand. Deliberately not "search all
 * brands for that handle": `handle` is unique per brand, not globally
 * (`webhook_inbounds_brand_id_handle_unique`), so a cross-brand search would
 * have to guess as soon as two brands pick the same name — and a webhook
 * config is a destination plus the credential that authenticates it, which is
 * the last thing in this package that may be resolved by guessing. The default
 * brand is where the brand-scoping migration put every pre-existing row, so an
 * install that flips `multi_brand` on keeps exactly the endpoints that worked
 * before it flipped, and no others.
 *
 * ## Scope, not just lookup
 *
 * The brand is set for the whole request, not only around the lookup. The
 * action dispatched at the end of the pipeline writes rows (entries, contacts,
 * log lines), and those are stamped with `brand-context`'s *current* brand at
 * write time. Resolving the endpoint under one brand and then writing its
 * results under another is the failure this middleware exists to prevent, in
 * its more expensive form.
 *
 * The previous value is restored on the way out. The manager is a singleton, so
 * in a long-lived process (Octane, a queue worker serving requests) a leftover
 * brand would be inherited by whatever ran next.
 */
class ResolveInboundBrand
{
    public function __construct(protected SystemLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $manager = app('brand-context');

        // Single-brand: the scope is a no-op, so there is nothing to set. The
        // brand-qualified URL shape still has to be answered consistently — it
        // is the shape the CP prints on every install — so a segment naming
        // some other brand is a 404 rather than a second working URL for the
        // same endpoint.
        if (! $manager->multiBrandEnabled()) {
            $named = (string) ($request->route('brand') ?? '');

            if ($named !== '' && $named !== $manager->default()->handle) {
                return $this->notFound();
            }

            return $next($request);
        }

        $previous = $manager->hasCurrent() ? $manager->current() : null;

        try {
            $brand = $this->resolve($request, $manager);

            if ($brand === null) {
                return $this->notFound();
            }

            $manager->setCurrent($brand);

            return $next($request);
        } finally {
            $manager->setCurrent($previous);
        }
    }

    /**
     * The brand this delivery belongs to, or null when the URL names one that
     * does not exist.
     */
    protected function resolve(Request $request, $manager): ?Brand
    {
        $named = $request->route('brand');

        if ($named === null || $named === '') {
            $this->logger->info('inbound_brand_defaulted',
                'Inbound delivery arrived without a brand segment; resolved to the default brand.', [
                    'handle' => (string) $request->route('handle'),
                    'canonical_url_shape' => '{prefix}/{brand}/{handle}',
                ]);

            return $manager->default();
        }

        $brand = Brand::query()->where('handle', $named)->first();

        if (! $brand) {
            // Same response as an unknown endpoint handle: the URL must not
            // tell an outsider which brands exist on this installation.
            $this->logger->warning('inbound_brand_not_found',
                "Inbound delivery named unknown brand '{$named}'.", [
                    'brand' => $named,
                    'handle' => (string) $request->route('handle'),
                ]);

            return null;
        }

        return $brand;
    }

    protected function notFound(): Response
    {
        return response()->json([
            'ok' => false,
            'error' => 'Endpoint not found or disabled.',
        ], 404);
    }
}
