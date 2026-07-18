<script setup>
/**
 * Compact dashboard stat tile — a small muted label (with optional icon)
 * sitting above a large value.
 *
 * Shared by the Overview and Insights dashboards so both render identical,
 * native Statamic card optics (design tokens, radius, spacing, dark mode).
 *
 * Previously both screens used the core `Widget` component for stat tiles.
 * `Widget` is a chart-sized container (`min-h-54` on both the card and its
 * inner column, plus a bordered header) whose unpadded body left a small
 * value stranded at the top-left of a tall, mostly-empty box. `Card` gives
 * the correct compact padding (`px-4.5 py-5 space-y-2`) with no forced
 * height, so the tile hugs its content.
 */
import { Card, Icon } from '@statamic/cms/ui';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: null },
});
</script>

<template>
    <Card>
        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
            <Icon v-if="icon" :name="icon" class="size-4 shrink-0" />
            <span>{{ label }}</span>
        </div>
        <div class="text-3xl font-bold leading-none tabular-nums text-gray-900 dark:text-gray-100">
            {{ value }}
        </div>
    </Card>
</template>
