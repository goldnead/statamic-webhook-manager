import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import UrlCell from '../../resources/js/components/UrlCell.vue';

/**
 * The cell every listing uses to show a URL.
 *
 * It exists because of two measured defects, and both are asserted here.
 *
 * 1. `MiddleEllipsis` cuts the middle out of a string. Webhook URLs of one
 *    service share their scheme, their host and their leading path and differ
 *    near the end, so the middle is precisely what identifies the row. Both of
 *    the playground's inbound endpoints rendered as `/webhook...-eingang` on
 *    05.09.2026 — the same eighteen characters for two different endpoints, at
 *    every column width.
 *
 * 2. That component estimates text width from a character table that exists
 *    for one font family (`inter`) and bills every character a full em when it
 *    misses. The Statamic 6.31 CP computes `ui-sans-serif` and `ui-monospace`,
 *    so the map always misses: a 278px box showed 21 characters where the font
 *    fits 38.
 *
 * The rule the tests below fix in place: the identifying tail leads, the head
 * follows underneath, and nothing is dropped.
 */

const halbmond = 'http://127.0.0.1:8099/webhooks/inbound/halbmond/crew-eingang';
const nordlicht = 'http://127.0.0.1:8099/webhooks/inbound/nordlicht/signatur-eingang';

function lines(wrapper) {
    return wrapper.findAll('span span').map((s) => s.text());
}

describe('UrlCell', () => {
    it('leads with the identifying tail and puts host and leading path underneath', () => {
        const wrapper = mount(UrlCell, { props: { url: halbmond } });

        expect(lines(wrapper)).toEqual([
            'halbmond/crew-eingang',
            '127.0.0.1:8099/webhooks/inbound',
        ]);
    });

    it('tells apart two URLs that share their head and their tail', () => {
        // The regression this component was written for. Under MiddleEllipsis
        // these two produced the same string.
        const a = lines(mount(UrlCell, { props: { url: halbmond } }))[0];
        const b = lines(mount(UrlCell, { props: { url: nordlicht } }))[0];

        expect(a).not.toBe(b);
        expect(a.startsWith('halbmond')).toBe(true);
        expect(b.startsWith('nordlicht')).toBe(true);
    });

    it('loses nothing: both lines together contain every segment of the URL', () => {
        const wrapper = mount(UrlCell, { props: { url: nordlicht } });
        const shown = lines(wrapper).join(' ');

        for (const segment of ['127.0.0.1:8099', 'webhooks', 'inbound', 'nordlicht', 'signatur-eingang']) {
            expect(shown).toContain(segment);
        }
    });

    it('keeps the whole URL on the title attribute for a hover', () => {
        const wrapper = mount(UrlCell, { props: { url: nordlicht } });

        expect(wrapper.attributes('title')).toBe(nordlicht);
    });

    it('keeps a query string with the tail, where it identifies the call', () => {
        const wrapper = mount(UrlCell, {
            props: { url: 'https://api.example.test/v1/hooks/receive?team=nord' },
        });

        expect(lines(wrapper)[0]).toBe('hooks/receive?team=nord');
    });

    it('shows a short path whole rather than moving half of it out of the way', () => {
        // Nothing in front worth demoting, so the path stays a path — leading
        // slash and all. A tail cut out of a longer path is not a path from
        // the root and deliberately has no slash.
        const wrapper = mount(UrlCell, { props: { url: 'https://example.test/hooks/demo' } });

        expect(lines(wrapper)).toEqual(['/hooks/demo', 'example.test']);
    });

    it('keeps the leading slash of a relative path in the head line', () => {
        // Inbound endpoints hand over `public_path`, which has no scheme. A
        // path fragment that quietly lost its leading slash reads as a
        // different value.
        const wrapper = mount(UrlCell, { props: { url: '/webhooks/inbound/nordlicht/studio-eingang' } });

        expect(lines(wrapper)).toEqual(['nordlicht/studio-eingang', '/webhooks/inbound']);
    });

    it('leaves a value it cannot parse alone instead of guessing at it', () => {
        // A URL still carrying an Antlers placeholder is not a URL yet.
        const roh = 'https://api.example.test/{{ tenant }}/hook';
        const wrapper = mount(UrlCell, { props: { url: roh } });

        expect(lines(wrapper).join(' ')).toContain('tenant');
    });

    it('renders nothing rather than a stray slash for an empty value', () => {
        const wrapper = mount(UrlCell, { props: { url: '' } });

        expect(lines(wrapper)).toEqual(['']);
    });
});
