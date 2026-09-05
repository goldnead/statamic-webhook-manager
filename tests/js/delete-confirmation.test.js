import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';

import OutboundEdit from '../../resources/js/pages/outbound/Edit.vue';
import InboundEdit from '../../resources/js/pages/inbound/Edit.vue';
import RulesEdit from '../../resources/js/pages/rules/Edit.vue';
import TemplatesEdit from '../../resources/js/pages/templates/Edit.vue';

/**
 * Every Edit page's delete confirmation, checked the same way.
 *
 * The defect: `inbound/Edit.vue` and `templates/Edit.vue` drove
 * `<ConfirmationModal>` with `v-if="showDelete"` and named the confirm button
 * with `confirm-text`. Neither is that component's API.
 *
 *   - `open` is the only prop that opens it. It defaults to `false`, so a
 *     ConfirmationModal that exists but was never handed `:open` renders an
 *     instance whose inner Modal stays shut. Nothing is portalled to the DOM,
 *     nothing is logged, and pressing Delete does exactly nothing — the same
 *     silent shape as the route-binding collision, one layer up.
 *   - `confirm-text` is not a prop at all (the real one is `buttonText`), so
 *     it lands in $attrs and the button keeps its default label, "Confirm".
 *   - `confirm-variant="danger"` is likewise not a prop; the danger styling is
 *     the boolean `danger`.
 *
 * `@cancel` is genuine and was never the problem — the component emits it on
 * dismiss — but on its own it cannot close what never opened.
 *
 * Outbound and Rules had it right all along and are here as the control: the
 * assertions below are identical for all four, so a page drifting away from
 * the working shape is what fails, not a page-specific expectation.
 *
 * `ConfirmationModal` is one of the stubbed CP components (tests/js/setup.js),
 * so this cannot assert "a dialog is visible" — it asserts what the page hands
 * the component, which is precisely where the defect is. Under the broken
 * version the stub is absent from the DOM entirely while `showDelete` is
 * false, and appears without `data-attr-open` once it is true.
 */

const pages = {
    Outbound: {
        component: OutboundEdit,
        props: {
            webhook: { id: 1, name: 'Ping', handle: 'ping', enabled: true, headers: [] },
            triggerOptions: { 'entry.saved': 'Entry saved' },
            authOptions: { none: 'None' },
            isNew: false,
            canDelete: true,
            saveUrl: '/cp/webhook-manager/outbound/1',
            deleteUrl: '/cp/webhook-manager/outbound/1',
            indexUrl: '/cp/webhook-manager/outbound',
        },
        // Outbound was translated through the addon's own namespace on
        // 05.09.2026 (F33); the test bootstrap's `__()` hands the key back, so
        // the key is what the modal receives. The other three still carry
        // plain global keys and are the reason `title`/`buttonText` are per
        // page here rather than one shared literal.
        title: 'webhook-manager::messages.cp.delete_webhook',
        buttonText: 'webhook-manager::messages.cp.delete',
    },
    Inbound: {
        component: InboundEdit,
        props: {
            endpoint: { id: 1, name: 'Receiver', handle: 'receiver', enabled: true },
            authOptions: { static_header: 'Static header' },
            actionOptions: { noop: 'Do nothing' },
            isNew: false,
            canDelete: true,
            saveUrl: '/cp/webhook-manager/inbound/1',
            deleteUrl: '/cp/webhook-manager/inbound/1',
            indexUrl: '/cp/webhook-manager/inbound',
        },
        title: 'Delete endpoint?',
        buttonText: 'Delete',
    },
    Rules: {
        component: RulesEdit,
        props: {
            rule: { id: 1, name: 'On save', handle: 'on-save', enabled: true, conditions: [] },
            triggerOptions: { 'entry.saved': 'Entry saved' },
            actionOptions: { webhook: 'Send webhook' },
            isNew: false,
            canDelete: true,
            saveUrl: '/cp/webhook-manager/rules/1',
            deleteUrl: '/cp/webhook-manager/rules/1',
            indexUrl: '/cp/webhook-manager/rules',
        },
        title: 'Delete rule',
        buttonText: 'Delete',
    },
    Templates: {
        component: TemplatesEdit,
        props: {
            template: { id: 1, name: 'Slack', handle: 'slack', type: 'antlers', body: '' },
            typeOptions: { antlers: 'Antlers' },
            namespaces: [],
            isNew: false,
            canDelete: true,
            saveUrl: '/cp/webhook-manager/templates/1',
            deleteUrl: '/cp/webhook-manager/templates/1',
            previewUrl: '/cp/webhook-manager/templates/1/preview',
            indexUrl: '/cp/webhook-manager/templates',
        },
        title: 'Delete Template',
        buttonText: 'Delete',
    },
};

function modal(wrapper) {
    return wrapper.find('[data-stub="ConfirmationModal"]');
}

describe.each(Object.entries(pages))('%s Edit', (_name, page) => {
    it('renders the delete confirmation, closed, before anything is pressed', () => {
        const wrapper = mount(page.component, { props: page.props });

        // Not "is it visible" — is it there at all, holding an `open` of false.
        // A page that mounts it behind `v-if="showDelete"` fails here: the
        // component only ever exists in the open state, and in that state it
        // was never given the prop that opens it.
        expect(modal(wrapper).exists()).toBe(true);
        expect(modal(wrapper).attributes('data-attr-open')).toBe('false');
    });

    it('opens the delete confirmation when showDelete flips', async () => {
        const wrapper = mount(page.component, { props: page.props });

        wrapper.vm.showDelete = true;
        await wrapper.vm.$nextTick();

        expect(modal(wrapper).attributes('data-attr-open')).toBe('true');
        expect(modal(wrapper).attributes('data-attr-title')).toBe(page.title);
    });

    it('labels the confirm button through the prop the component actually reads', async () => {
        const wrapper = mount(page.component, { props: page.props });

        wrapper.vm.showDelete = true;
        await wrapper.vm.$nextTick();

        // `buttonText`, not `confirmText`. With the wrong name the attribute is
        // simply absent and the button silently reads "Confirm".
        expect(modal(wrapper).attributes('data-attr-button-text')).toBe(page.buttonText);
        expect(modal(wrapper).attributes('data-attr-confirm-text')).toBeUndefined();
    });
});
