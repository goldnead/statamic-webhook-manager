<?php

namespace Goldnead\WebhookManager\Storage;

use Illuminate\Database\Eloquent\Model;

/**
 * Converts between an Eloquent model and the array shape persisted to a
 * YAML file by the flat-file driver — and back again — without touching
 * the database.
 *
 * The same Eloquent model classes are reused as data carriers for both
 * drivers, so all casts, accessors and the encrypted `auth_config`
 * attribute behave identically regardless of where the record is stored.
 *
 * Two transforms are applied so the on-disk YAML stays human-readable and
 * git-diffable:
 *   - `array`-cast attributes are stored as native YAML lists/maps rather
 *     than the JSON strings Eloquent keeps internally.
 *   - Custom-cast attributes (e.g. the Crypt-encrypted `auth_config`) are
 *     left as their raw stored value, so secrets remain encrypted at rest.
 */
class ModelHydrator
{
    /**
     * Model attributes shaped for YAML storage.
     *
     * @return array<string,mixed>
     */
    public function toStorage(Model $model): array
    {
        $data = $model->getAttributes();

        foreach ($model->getCasts() as $key => $cast) {
            if ($cast === 'array' && isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = json_decode($data[$key], true);
            }
        }

        return $data;
    }

    /**
     * Rebuild a model instance from a YAML-storage array, marking it as an
     * existing (already-persisted) record so the CP treats it like a
     * database-backed model.
     *
     * @param  class-string<Model>  $class
     * @param  array<string,mixed>  $data
     */
    public function fromStorage(string $class, array $data): Model
    {
        /** @var Model $model */
        $model = new $class;

        foreach ($model->getCasts() as $key => $cast) {
            if ($cast === 'array' && isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = json_encode($data[$key]);
            }
        }

        $model->setRawAttributes($data, sync: true);
        $model->exists = true;

        return $model;
    }
}
