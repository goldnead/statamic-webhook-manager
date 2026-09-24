<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Triggers\AssetSavedTrigger;
use Goldnead\WebhookManager\Triggers\EntryDeletedTrigger;
use Goldnead\WebhookManager\Triggers\EntryPublishedTrigger;
use Goldnead\WebhookManager\Triggers\EntrySavedTrigger;
use Goldnead\WebhookManager\Triggers\EntryUnpublishedTrigger;
use Goldnead\WebhookManager\Triggers\FormSubmittedTrigger;
use Goldnead\WebhookManager\Triggers\UserSavedTrigger;

/**
 * Central registry of internal triggers.
 *
 * Listeners normalise framework events to a TriggerEvent via the trigger
 * implementation registered here. Third-party packages can register
 * additional triggers through this registry.
 */
class TriggerRegistry
{
    /** @var array<string, TriggerInterface> */
    protected array $triggers = [];

    public function register(TriggerInterface $trigger): void
    {
        $this->triggers[$trigger->handle()] = $trigger;
    }

    public function get(string $handle): ?TriggerInterface
    {
        return $this->triggers[$handle] ?? null;
    }

    public function has(string $handle): bool
    {
        return isset($this->triggers[$handle]);
    }

    /** @return array<string, TriggerInterface> */
    public function all(): array
    {
        return $this->triggers;
    }

    /**
     * For CP <select> options. Returns ["handle" => "label"].
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $opts = [];
        foreach ($this->triggers as $t) {
            $opts[$t->handle()] = $t->label();
        }
        ksort($opts);

        return $opts;
    }

    /**
     * For the grouped trigger picker: one row per trigger, grouped by the
     * trigger's source type and sorted by group heading, then by label.
     *
     * `label` is the full label ("Zahlungen: Zahlung eingegangen") and is what
     * a closed picker shows. `short_label` drops the group prefix, because
     * under the heading "Zahlungen" the prefix only repeats itself.
     *
     * The heading comes from `webhook-manager::messages.trigger_groups.<type>`.
     * A type nobody translated takes the prefix all of its labels share
     * ("Shop: …" → "Shop"), and failing that its handle as it is.
     *
     * @return list<array{value: string, label: string, short_label: string, group: string, group_label: string}>
     */
    public function groupedOptions(): array
    {
        /** @var array<string, array<string, string>> $groups */
        $groups = [];
        foreach ($this->triggers as $t) {
            $groups[$t->sourceType()][$t->handle()] = $t->label();
        }

        $rows = [];
        foreach ($groups as $type => $labels) {
            $type = (string) $type;
            $prefix = $this->sharedPrefix(array_values($labels));
            $heading = $this->groupHeading($type, $prefix);

            foreach ($labels as $handle => $label) {
                $rows[] = [
                    'value' => (string) $handle,
                    'label' => $label,
                    'short_label' => $prefix === null ? $label : $this->shorten($label),
                    'group' => $type,
                    'group_label' => $heading,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['group_label'], $b['group_label'])
            ?: strcmp($a['group'], $b['group'])
            ?: strnatcasecmp($a['short_label'], $b['short_label']));

        return $rows;
    }

    protected function groupHeading(string $type, ?string $prefix): string
    {
        $key = 'webhook-manager::messages.trigger_groups.'.$type;
        $translated = __($key);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        return $prefix ?? $type;
    }

    /**
     * The part before ": " when every label of a group starts with the same
     * one, else null.
     *
     * @param  list<string>  $labels
     */
    protected function sharedPrefix(array $labels): ?string
    {
        $prefix = null;
        foreach ($labels as $label) {
            $pos = mb_strpos($label, ': ');
            if ($pos === false) {
                return null;
            }

            $own = mb_substr($label, 0, $pos);
            if ($prefix !== null && $own !== $prefix) {
                return null;
            }
            $prefix = $own;
        }

        return $prefix;
    }

    protected function shorten(string $label): string
    {
        $rest = mb_substr($label, (int) mb_strpos($label, ': ') + 2);

        return mb_strtoupper(mb_substr($rest, 0, 1)).mb_substr($rest, 1);
    }

    public function registerDefaults(): void
    {
        $this->register(new EntrySavedTrigger);
        $this->register(new EntryPublishedTrigger);
        $this->register(new EntryUnpublishedTrigger);
        $this->register(new EntryDeletedTrigger);
        $this->register(new FormSubmittedTrigger);
        $this->register(new UserSavedTrigger);
        $this->register(new AssetSavedTrigger);
    }
}
