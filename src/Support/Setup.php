<?php

namespace Goldnead\WebhookManager\Support;

use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The check a CP listing runs before its first query.
 *
 * An installation that has the package but has never run its migrations
 * answers HTTP 500 on every screen of this addon: the nav item is there, the
 * route resolves, and the first query in `index()` throws
 * `no such table: webhook_outbounds` while the page is being built. That is an
 * operator's unfinished setup, not a defect, and it owes the reader a sentence
 * rather than a stack trace.
 *
 * The reason must not vanish with the 500, though: every guarded page that
 * turns somebody away writes why to the log first. A page that renders an
 * empty state and says nothing anywhere would be worse than the crash it
 * replaced — the install would look finished and never work.
 */
final class Setup
{
    /**
     * The setup screen for a CP listing, or null when the page can run.
     *
     * @param  string  $title  The page's own heading, so the screen still reads as that page.
     * @param  string  ...$tables  Every table the listing touches while rendering.
     */
    public static function guard(string $title, string ...$tables): ?Response
    {
        $missing = array_values(array_filter(
            $tables,
            fn (string $table) => ! Schema::hasTable($table)
        ));

        if ($missing === []) {
            return null;
        }

        Log::error(sprintf(
            'statamic-webhook-manager: the CP page "%s" cannot load because these database tables do not exist: %s. Run `php artisan migrate`.',
            $title,
            implode(', ', $missing)
        ));

        return Inertia::render('webhook-manager::SetupRequired', [
            'title' => $title,
            'heading' => __('webhook-manager::messages.setup_required_heading'),
            'description' => __('webhook-manager::messages.setup_required_description'),
            'tables' => $missing,
        ]);
    }

    /**
     * The configuration tables a page touches — none under the flat-file driver.
     *
     * Outbound webhooks, inbound endpoints, rules and templates live either in
     * the database or in YAML files, depending on the active storage driver
     * ({@see StorageDriverManager}). Under `flat` those four tables are never
     * queried, so demanding them would send a perfectly working installation
     * to the setup screen — the exact opposite of the point.
     *
     * Deliveries and logs are database-only whatever the driver says, so they
     * go to {@see guard()} directly and never through here.
     *
     * @return array<int, string>
     */
    public static function configTables(string ...$tables): array
    {
        return app(StorageDriverManager::class)->current() === 'flat' ? [] : $tables;
    }
}
