<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Storage\StorageMigrator;
use Illuminate\Console\Command;

/**
 * Move webhook *configuration* (outbound webhooks, inbound endpoints, rules,
 * templates) between the database and flat-file storage drivers.
 *
 * Records are copied id-for-id and uuid-for-uuid so the database-backed
 * delivery/log history keeps resolving to the right webhook after a switch.
 * The target store for each entity is cleared first, so a migration is a
 * clean import rather than a merge.
 *
 *   php artisan webhook-manager:storage:migrate --from=eloquent --to=flat
 *   php artisan webhook-manager:storage:migrate --from=flat --to=eloquent --dry-run
 */
class StorageMigrateCommand extends Command
{
    protected $signature = 'webhook-manager:storage:migrate
        {--from= : Source driver (eloquent|flat)}
        {--to= : Target driver (eloquent|flat)}
        {--dry-run : Report what would be copied without writing}';

    protected $description = 'Migrate webhook configuration between the database and flat-file storage drivers.';

    public function handle(StorageMigrator $migrator): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $dry = (bool) $this->option('dry-run');

        if (! in_array($from, StorageMigrator::DRIVERS, true) || ! in_array($to, StorageMigrator::DRIVERS, true)) {
            $this->error('Both --from and --to must be one of: '.implode(', ', StorageMigrator::DRIVERS).'.');

            return self::FAILURE;
        }
        if ($from === $to) {
            $this->error('--from and --to must differ.');

            return self::FAILURE;
        }

        $this->info(sprintf('Migrating webhook config: %s → %s%s', $from, $to, $dry ? ' (dry run)' : ''));

        if ($dry) {
            foreach ($migrator->counts($from) as $label => $count) {
                $this->line(sprintf('  %s: %d record(s)', $label, $count));
            }
            $this->comment('Dry run only — nothing was written.');

            return self::SUCCESS;
        }

        foreach ($migrator->migrate($from, $to) as $label => $count) {
            $this->line(sprintf('  %s: %d record(s)', $label, $count));
        }

        $this->info("Done. The Control Panel → Settings → Storage screen reflects the active driver, or set WEBHOOK_MANAGER_DRIVER={$to}.");

        return self::SUCCESS;
    }
}
