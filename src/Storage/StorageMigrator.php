<?php

namespace Goldnead\WebhookManager\Storage;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentInboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentOutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentRuleRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentTemplateRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Copies webhook *configuration* (outbound webhooks, inbound endpoints,
 * rules, templates) between the database and flat-file storage drivers,
 * id-for-id, so the database-backed delivery/log history keeps resolving
 * to the right webhook after a switch.
 *
 * Used by both the `storage:migrate` console command and the Control Panel
 * "switch storage" action. Delivery records and logs are never touched —
 * they always live in the database.
 */
class StorageMigrator
{
    public const DRIVERS = ['eloquent', 'flat'];

    public function __construct(
        protected FileStore $files,
        protected ModelHydrator $hydrator,
    ) {
    }

    /**
     * @return array<string,array{model:class-string<Model>,eloquent:class-string,subdir:string}>
     */
    public function entities(): array
    {
        return [
            'outbound webhooks' => ['model' => OutboundWebhook::class, 'eloquent' => EloquentOutboundWebhookRepository::class, 'subdir' => 'outbound'],
            'inbound endpoints' => ['model' => InboundEndpoint::class, 'eloquent' => EloquentInboundEndpointRepository::class, 'subdir' => 'inbound'],
            'rules' => ['model' => Rule::class, 'eloquent' => EloquentRuleRepository::class, 'subdir' => 'rules'],
            'templates' => ['model' => Template::class, 'eloquent' => EloquentTemplateRepository::class, 'subdir' => 'templates'],
        ];
    }

    /**
     * Record count per entity for a driver, without copying anything.
     *
     * @return array<string,int>
     */
    public function counts(string $driver): array
    {
        $counts = [];
        foreach ($this->entities() as $label => $meta) {
            $counts[$label] = $this->readAll($driver, $meta)->count();
        }

        return $counts;
    }

    /**
     * Copy every entity from one driver to the other. The target store is
     * cleared first, so this is a clean import rather than a merge.
     *
     * @return array<string,int>  copied count per entity
     */
    public function migrate(string $from, string $to): array
    {
        $copied = [];
        foreach ($this->entities() as $label => $meta) {
            $records = $this->readAll($from, $meta);
            $copied[$label] = $records->count();

            if ($records->isEmpty()) {
                $this->clearTarget($to, $meta);
                continue;
            }

            $this->clearTarget($to, $meta);
            foreach ($records as $model) {
                $this->writeRecord($to, $meta, $model);
            }
        }

        return $copied;
    }

    /**
     * @param  array{model:class-string<Model>,eloquent:class-string,subdir:string}  $meta
     * @return Collection<int, Model>
     */
    protected function readAll(string $driver, array $meta): Collection
    {
        if ($driver === 'eloquent') {
            return app($meta['eloquent'])->all();
        }

        return collect($this->files->glob($meta['subdir'].'/*.yaml'))
            ->map(fn (string $relative) => $this->files->readYaml($relative))
            ->filter(fn (array $data) => ! empty($data['id']))
            ->map(fn (array $data) => $this->hydrator->fromStorage($meta['model'], $data))
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

        foreach ($this->files->glob($meta['subdir'].'/*.yaml') as $relative) {
            $this->files->delete($relative);
        }
    }

    /**
     * @param  array{model:class-string<Model>,eloquent:class-string,subdir:string}  $meta
     */
    protected function writeRecord(string $driver, array $meta, Model $model): void
    {
        if ($driver === 'flat') {
            $this->files->writeYaml(
                $meta['subdir'].'/'.$model->handle.'.yaml',
                $this->hydrator->toStorage($model),
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
