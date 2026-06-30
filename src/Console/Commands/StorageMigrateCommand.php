<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentInboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentOutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentRuleRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentTemplateRepository;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Storage\ModelHydrator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * @var array<string,array{model:class-string<Model>,eloquent:class-string,subdir:string}>
     */
    protected array $entities = [];

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $dry = (bool) $this->option('dry-run');

        if (! in_array($from, ['eloquent', 'flat'], true) || ! in_array($to, ['eloquent', 'flat'], true)) {
            $this->error('Both --from and --to must be one of: eloquent, flat.');

            return self::FAILURE;
        }
        if ($from === $to) {
            $this->error('--from and --to must differ.');

            return self::FAILURE;
        }

        $this->entities = [
            'outbound webhooks' => ['model' => OutboundWebhook::class, 'eloquent' => EloquentOutboundWebhookRepository::class, 'subdir' => 'outbound'],
            'inbound endpoints' => ['model' => InboundEndpoint::class, 'eloquent' => EloquentInboundEndpointRepository::class, 'subdir' => 'inbound'],
            'rules' => ['model' => Rule::class, 'eloquent' => EloquentRuleRepository::class, 'subdir' => 'rules'],
            'templates' => ['model' => Template::class, 'eloquent' => EloquentTemplateRepository::class, 'subdir' => 'templates'],
        ];

        $this->info(sprintf('Migrating webhook config: %s → %s%s', $from, $to, $dry ? ' (dry run)' : ''));

        foreach ($this->entities as $label => $meta) {
            $records = $this->readAll($from, $meta);
            $this->line(sprintf('  %s: %d record(s)', $label, $records->count()));

            if ($dry || $records->isEmpty()) {
                continue;
            }

            $this->clearTarget($to, $meta);
            foreach ($records as $model) {
                $this->writeRecord($to, $meta, $model);
            }
        }

        if ($dry) {
            $this->comment('Dry run only — nothing was written.');

            return self::SUCCESS;
        }

        $this->info("Done. Set WEBHOOK_MANAGER_DRIVER={$to} (or storage.driver in config) to use the migrated data.");

        return self::SUCCESS;
    }

    /**
     * @param  array{model:class-string<Model>,eloquent:class-string,subdir:string}  $meta
     * @return \Illuminate\Support\Collection<int, Model>
     */
    protected function readAll(string $driver, array $meta): \Illuminate\Support\Collection
    {
        if ($driver === 'eloquent') {
            return app($meta['eloquent'])->all();
        }

        $files = app(FileStore::class);
        $hydrator = app(ModelHydrator::class);

        return collect($files->glob($meta['subdir'].'/*.yaml'))
            ->map(fn (string $relative) => $files->readYaml($relative))
            ->filter(fn (array $data) => ! empty($data['id']))
            ->map(fn (array $data) => $hydrator->fromStorage($meta['model'], $data))
            ->values();
    }

    /**
     * @param  array{model:class-string<Model>,eloquent:class-string,subdir:string}  $meta
     */
    protected function clearTarget(string $driver, array $meta): void
    {
        if ($driver === 'eloquent') {
            ($meta['model'])::query()->delete();

            return;
        }

        $files = app(FileStore::class);
        foreach ($files->glob($meta['subdir'].'/*.yaml') as $relative) {
            $files->delete($relative);
        }
    }

    /**
     * @param  array{model:class-string<Model>,eloquent:class-string,subdir:string}  $meta
     */
    protected function writeRecord(string $driver, array $meta, Model $model): void
    {
        if ($driver === 'flat') {
            app(FileStore::class)->writeYaml(
                $meta['subdir'].'/'.$model->handle.'.yaml',
                app(ModelHydrator::class)->toStorage($model),
            );

            return;
        }

        // Eloquent target: insert preserving the original id/uuid.
        $class = $meta['model'];
        /** @var Model $new */
        $new = new $class;
        $new->setRawAttributes($model->getAttributes(), sync: true);
        $new->exists = false;
        $new->save();
    }
}
