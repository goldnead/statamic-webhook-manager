<script setup>
import { computed } from 'vue';

/**
 * One URL in one table cell.
 *
 * Two separate defects met in this cell, and each one alone was enough to make
 * a URL column worthless.
 *
 * 1. `MiddleEllipsis` throws away the MIDDLE of a string, which for a URL is
 *    exactly the wrong half. Endpoints of the same service share their scheme,
 *    their host and their leading path and differ near the end; measured in the
 *    playground on 05.09.2026, both of these rendered as `/webhook...-eingang`:
 *
 *        http://127.0.0.1:8099/webhooks/inbound/halbmond/crew-eingang
 *        http://127.0.0.1:8099/webhooks/inbound/nordlicht/signatur-eingang
 *
 *    No column width fixes that — a wider cell just cuts a little less out of
 *    the middle. So the halves are swapped here: the identifying tail leads.
 *    The last path segments (plus query and fragment) go on the first line,
 *    everything in front of them — host and leading path — on the muted second
 *    line the listings already use for a webhook's handle. Nothing is dropped.
 *
 * 2. `MiddleEllipsis` does not measure text, it estimates it from a character
 *    table, and that table exists for exactly one font family: `inter`. Its
 *    lookup takes the FIRST family of the computed `font-family` and falls back
 *    to `BASE_FONT_SIZE` (16) per character when it misses. The Statamic 6.31
 *    CP computes `ui-sans-serif` for body text and `ui-monospace` for
 *    `font-mono` — the map misses both, so every character is billed a full em.
 *    Measured: a 278px box showed 21 characters where the mono font fits 38.
 *    Hence `truncate` here, which is what core itself uses for a long cell
 *    value (Logs/Index.vue) and is pixel-accurate. It cuts the end, which is
 *    harmless once the identifying part has been moved to the front.
 */
const props = defineProps({
    /** Full URL as stored. May be empty, relative, or carry Antlers placeholders. */
    url: { type: String, default: '' },
    /** How many trailing path segments count as the identifying tail. */
    tailSegments: { type: Number, default: 2 },
});

/**
 * Split into [tail, head]. Anything that is not an absolute URL — a relative
 * path, a value with `{{ }}` still in it — is left whole on the first line
 * rather than guessed at.
 */
const teile = computed(() => {
    const roh = props.url || '';
    if (! roh) return { schwanz: '', kopf: '' };

    let host = '';
    let pfad = roh;
    let anhang = '';

    try {
        const u = new URL(roh);
        host = u.host;
        pfad = u.pathname;
        anhang = `${u.search}${u.hash}`;
    } catch {
        // Not an absolute URL. Still worth splitting on its own segments.
    }

    const segmente = pfad.split('/').filter((s) => s !== '');
    if (segmente.length <= props.tailSegments) {
        // Nothing in front worth moving down; keep the path whole.
        return { schwanz: (pfad || roh) + anhang, kopf: host };
    }

    const schwanz = segmente.slice(-props.tailSegments);
    const kopf = segmente.slice(0, -props.tailSegments);

    // Without a host the head is a path fragment, and a path fragment that
    // silently lost its leading slash reads like a different value. Inbound
    // endpoints are exactly this case: their `public_path` is relative.
    const kopfPfad = kopf.join('/');

    return {
        schwanz: schwanz.join('/') + anhang,
        kopf: host ? [host, kopfPfad].filter(Boolean).join('/') : '/'.concat(kopfPfad),
    };
});
</script>

<template>
    <span class="block min-w-0" :title="url">
        <span class="block truncate font-mono text-xs text-gray-900 dark:text-gray-100">{{ teile.schwanz }}</span>
        <span
            v-if="teile.kopf"
            class="block truncate font-mono text-2xs text-gray-500 dark:text-gray-400"
        >{{ teile.kopf }}</span>
    </span>
</template>
