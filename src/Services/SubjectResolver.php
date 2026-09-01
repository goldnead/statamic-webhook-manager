<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Works out which object a trigger event was about.
 *
 * A delivery row records what was sent and to whom; the subject records
 * *what it concerned* — the payment, the offer, the entry — so the delivery
 * log can be read from the object's side. Resolution runs once, when the
 * snapshot is written, and never changes afterwards.
 *
 * Order, first hit wins:
 *
 *   1. The payload names its subject outright: scalar, non-empty
 *      `subject_type` and `subject_id`.
 *   2. A configured type's key is present in the payload
 *      (`webhook-manager.subjects.<type>.keys`, dotted paths allowed).
 *   3. The trigger handle matches one of a configured type's patterns
 *      (`webhook-manager.subjects.<type>.triggers`) and the event carries a
 *      source reference, else a top-level `id` in the payload.
 *   4. The event's own `sourceType` and `sourceReference`, unless the type is
 *      the generic `event` — that is how the built-in entry / user / asset /
 *      form-submission triggers get a subject without any configuration.
 *
 * Anything else resolves to null and the columns stay empty.
 */
class SubjectResolver
{
    private const MAX_LENGTH = 64;

    /**
     * @param  array<string, array{keys?: list<string>, triggers?: list<string>}>|null  $subjects
     *                                                                                             Defaults to `webhook-manager.subjects`.
     */
    public function __construct(private ?array $subjects = null) {}

    /**
     * @return array{type: string, id: string}|null
     */
    public function resolve(TriggerEvent $event): ?array
    {
        $payload = $event->payload;

        if ($this->isUsable($payload['subject_type'] ?? null) && $this->isUsable($payload['subject_id'] ?? null)) {
            return $this->make($payload['subject_type'], $payload['subject_id']);
        }

        foreach ($this->subjects() as $type => $config) {
            foreach ((array) ($config['keys'] ?? []) as $key) {
                $value = Arr::get($payload, $key);

                if ($this->isUsable($value)) {
                    return $this->make($type, $value);
                }
            }
        }

        foreach ($this->subjects() as $type => $config) {
            foreach ((array) ($config['triggers'] ?? []) as $pattern) {
                if (! Str::is($pattern, $event->triggerHandle)) {
                    continue;
                }

                $reference = $event->sourceReference ?? ($payload['id'] ?? null);

                if ($this->isUsable($reference)) {
                    return $this->make($type, $reference);
                }
            }
        }

        if (! in_array($event->sourceType, ['', 'event'], true) && $this->isUsable($event->sourceReference)) {
            return $this->make($event->sourceType, $event->sourceReference);
        }

        return null;
    }

    /**
     * @return array<string, array{keys?: list<string>, triggers?: list<string>}>
     */
    private function subjects(): array
    {
        return $this->subjects ??= (array) config('webhook-manager.subjects', []);
    }

    /**
     * @return array{type: string, id: string}
     */
    private function make(mixed $type, mixed $id): array
    {
        return [
            'type' => Str::limit(Str::lower(trim((string) $type)), self::MAX_LENGTH, ''),
            'id' => Str::limit(trim((string) $id), self::MAX_LENGTH, ''),
        ];
    }

    private function isUsable(mixed $value): bool
    {
        return is_scalar($value) && ! is_bool($value) && trim((string) $value) !== '';
    }
}
