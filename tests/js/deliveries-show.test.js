import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/deliveries/Show.vue';

/**
 * The Response panel of the delivery detail view.
 *
 * The defect this file exists for: `contentTypeMode()` did
 *
 *     (headers?.['content-type'] ?? '').toLowerCase()
 *
 * PSR-7 / Guzzle response headers are `{"content-type": ["application/json"]}`
 * — the value is an ARRAY, `.toLowerCase()` is not a function on it, and the
 * TypeError happens during render, so it does not blank one badge, it takes
 * the whole Response panel with it: status code, duration, headers, body.
 * A FAILED delivery rendered fine, because it carries no response headers at
 * all. The panel was therefore missing exactly where someone goes looking.
 *
 * These tests mount the real component. If a helper throws during render,
 * `mount()` throws and the test fails — which is the point: the assertion is
 * not "the function returns a string", it is "the panel is still there".
 *
 * A structural guard against the same regression lives in
 * tests/Feature/DeliveryShowHandlesArrayHeadersTest.php. The two are not
 * redundant: the PHP test catches a newly added component that reintroduces
 * the pattern, this one catches the logic getting it wrong.
 */

function delivery(overrides = {}) {
    return {
        id: 42,
        status: 'success',
        method: 'POST',
        url: 'https://receiver.example.test/hook',
        response_code: 200,
        duration_ms: 128,
        attempts: 1,
        correlation_id: 'whm_01hx',
        trigger_label: 'Entry saved',
        trigger_type: 'entry.saved',
        curl: "curl -X POST 'https://receiver.example.test/hook'",
        can_replay: false,
        request: { headers: { 'content-type': 'application/json' }, body: '{"a":1}' },
        response: { headers: {}, body: '' },
        ...overrides,
    };
}

function mountShow(deliveryOverrides = {}) {
    return mount(Show, {
        props: {
            delivery: delivery(deliveryOverrides),
            indexUrl: '/cp/webhook-manager/deliveries',
            replayUrl: null,
        },
    });
}

/** The Response panel, located by its heading rather than by position. */
function responsePanel(wrapper) {
    // The heading is the translation KEY here: the test bootstrap's `__()`
    // returns what it is given. The keys are namespaced (`webhook-manager::`)
    // because a global JSON key is redefinable by any sibling addon — and one
    // of them does redefine "Delivery".
    const panel = wrapper.find('[data-stub="Panel"][data-attr-heading="webhook-manager::messages.cp.response"]');

    expect(panel.exists(), 'The Response panel did not render at all.').toBe(true);

    return panel;
}

/** The CodeEditor mode chosen for the response body. */
function responseBodyMode(wrapper) {
    const editors = responsePanel(wrapper).findAll('[data-stub="CodeEditor"]');

    // headers editor, body editor
    expect(editors).toHaveLength(2);

    return editors[1].attributes('data-attr-mode');
}

describe('deliveries/Show — response content type', () => {
    it('survives a PSR-7 array-valued content-type', () => {
        const wrapper = mountShow({
            response: { headers: { 'content-type': ['application/json'] }, body: '{"ok":true}' },
        });

        expect(responseBodyMode(wrapper)).toBe('json');
    });

    it('survives a canonically cased array-valued header', () => {
        const wrapper = mountShow({
            response: { headers: { 'Content-Type': ['text/html; charset=UTF-8'] }, body: '<p>hi</p>' },
        });

        expect(responseBodyMode(wrapper)).toBe('html');
    });

    it('still handles the plain string form', () => {
        const wrapper = mountShow({
            response: { headers: { 'content-type': 'application/xml' }, body: '<a/>' },
        });

        expect(responseBodyMode(wrapper)).toBe('xml');
    });

    it('falls back to body sniffing when the header is absent', () => {
        const wrapper = mountShow({
            response: { headers: {}, body: '{"sniffed":true}' },
        });

        expect(responseBodyMode(wrapper)).toBe('json');
    });

    it('handles a failed delivery that has no response at all', () => {
        const wrapper = mountShow({
            status: 'failed',
            response_code: null,
            response: null,
            error: 'Connection timed out',
            error_type: 'timeout',
        });

        expect(responseBodyMode(wrapper)).toBe('text');
    });

    it('handles a multi-valued header without losing the type', () => {
        const wrapper = mountShow({
            response: {
                headers: { 'content-type': ['application/json', 'charset=utf-8'] },
                body: '{"ok":true}',
            },
        });

        expect(responseBodyMode(wrapper)).toBe('json');
    });
});

describe('deliveries/Show — the response panel itself', () => {
    /**
     * The regression was never really about a mode string. It was that the
     * whole panel disappeared on exactly the deliveries that succeeded.
     */
    it('renders status code, duration, headers and body on a successful delivery', () => {
        const wrapper = mountShow({
            response: { headers: { 'content-type': ['application/json'] }, body: '{"ok":true}' },
        });

        const panel = responsePanel(wrapper);
        const text = panel.text();

        expect(text).toContain('webhook-manager::messages.cp.status_code');
        expect(text).toContain('200');
        expect(text).toContain('128 ms');
        expect(text).toContain('webhook-manager::messages.cp.headers');
        expect(text).toContain('webhook-manager::messages.cp.body');

        // The headers editor shows the raw PSR-7 shape, unflattened.
        const editors = panel.findAll('[data-stub="CodeEditor"]');
        expect(editors[0].attributes('data-attr-model-value')).toContain('application/json');
        expect(editors[1].attributes('data-attr-model-value')).toBe('{"ok":true}');
    });

    it('renders the delivery facts the controller has always computed', () => {
        const wrapper = mountShow();
        const text = wrapper.text();

        expect(text).toContain('Entry saved');
        expect(wrapper.find('[data-testid="correlation-id"]').text()).toBe('whm_01hx');
        const curlEditor = wrapper.find('[data-testid="curl"]');
        expect(curlEditor.exists(), 'The pre-computed cURL command is not rendered.').toBe(true);
        expect(curlEditor.attributes('data-attr-model-value'))
            .toBe("curl -X POST 'https://receiver.example.test/hook'");
    });
});
