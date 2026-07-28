import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';

import OutboundEdit from '../../resources/js/pages/outbound/Edit.vue';
import InboundEdit from '../../resources/js/pages/inbound/Edit.vue';
import RulesEdit from '../../resources/js/pages/rules/Edit.vue';
import TemplatesEdit from '../../resources/js/pages/templates/Edit.vue';
import IntegrationSetup from '../../resources/js/pages/integrations/Setup.vue';
import SettingsIndex from '../../resources/js/pages/settings/Index.vue';

/**
 * Does a rejected input actually reach the screen?
 *
 * The structural guard (CpValidationVisibilityTest) reads the sources and can
 * say that a page mentions `form.errors.conditions` somewhere. It cannot say
 * whether the element carrying it renders — the mask may sit behind a `v-if`
 * that is false in exactly the state where the error occurs, and in marketing
 * v1.5.3 that is precisely what happened to `handle`: declared as handled at
 * the field, but the field only existed while creating, so editing showed the
 * message nowhere. Only a mount catches that.
 *
 * So: for every key the server-side FormRequest validates, hand the page that
 * error and require the text to be somewhere in the rendered DOM — at its own
 * field (`Field :error` → `data-attr-error` on the stub) or in the collected
 * banner above the mask (`data-webhook-form-errors`). Where it lands is the
 * page's business; that it lands at all is not negotiable.
 */

afterEach(() => {
    globalThis.__TEST_FORM_ERRORS__ = {};
});

/** Mount `component` as if the server had just rejected `errors`. */
function mountWithErrors(component, props, errors) {
    globalThis.__TEST_FORM_ERRORS__ = errors;

    return mount(component, { props });
}

/** Every place an error message can legitimately end up, as plain strings. */
function visibleMessages(wrapper) {
    const atFields = wrapper
        .findAll('[data-attr-error]')
        .map((el) => el.attributes('data-attr-error'));

    const inBanner = wrapper
        .findAll('[data-webhook-form-errors]')
        .map((el) => el.text());

    return [...atFields, ...inBanner];
}

/**
 * The keys each page's FormRequest validates, mirrored from
 * src/Http/Requests/*.php. Nested keys (`retry_strategy.max_attempts`) come
 * back from Laravel under their full dotted name, which is what a page has to
 * cope with — so they are tested under that name.
 */
const pages = {
    'inbound/Edit': {
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
        keys: [
            'name', 'handle', 'enabled', 'path', 'allowed_methods', 'auth_type',
            'auth_config', 'auth_config_json', 'expected_content_type',
            'max_payload_kb', 'replay_protection_enabled', 'rate_limit_config',
            'logging_mode', 'mapping_config', 'action_type', 'action_config',
            'response_config',
        ],
    },
    'outbound/Edit': {
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
        keys: [
            'name', 'handle', 'description', 'enabled', 'trigger_type',
            'trigger_config', 'url', 'method', 'headers', 'timeout_seconds',
            'follow_redirects', 'auth_type', 'auth_config', 'auth_config_json',
            'payload_type', 'payload_template', 'payload_template_handle',
            'conditions', 'retry_strategy', 'retry_strategy.strategy',
            'retry_strategy.max_attempts', 'queue_enabled',
            'idempotency_enabled', 'log_body_mode', 'success_matcher',
        ],
    },
    'rules/Edit': {
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
        keys: [
            'name', 'handle', 'enabled', 'trigger_type', 'trigger_config',
            'conditions', 'actions', 'stop_on_failure', 'order_index',
        ],
    },
    'templates/Edit': {
        component: TemplatesEdit,
        props: {
            template: { id: 1, name: 'Body', handle: 'body', type: 'outbound_body', body: '{}' },
            typeOptions: { outbound_body: 'Outbound body' },
            isNew: false,
            canDelete: true,
            saveUrl: '/cp/webhook-manager/templates/1',
            deleteUrl: '/cp/webhook-manager/templates/1',
            previewUrl: '/cp/webhook-manager/templates/preview',
            indexUrl: '/cp/webhook-manager/templates',
        },
        keys: ['name', 'handle', 'type', 'body', 'meta'],
    },
    'integrations/Setup': {
        component: IntegrationSetup,
        props: {
            preset: {
                handle: 'slack',
                label: 'Slack',
                icon: 'bell',
                fields: [{ handle: 'slack_url', label: 'Webhook URL', required: true }],
            },
            triggerOptions: { 'entry.published': 'Entry published' },
            galleryUrl: '/cp/webhook-manager/integrations',
            saveUrl: '/cp/webhook-manager/integrations/slack',
        },
        // PresetController validates name + trigger_type + every preset field
        // handle, so the field list is part of the contract.
        keys: ['name', 'trigger_type', 'slack_url'],
    },
    'settings/Index': {
        component: SettingsIndex,
        props: {
            // Shape mirrors SettingsController::extractConfig(); the page reads
            // every group unguarded, so an empty object never renders.
            config: {
                general: {}, defaults: {}, reliability: {}, security: {}, logging: {},
            },
            rawConfig: '{}',
            configFilePath: '/app/config/webhook-manager.php',
            isEditable: false,
            storage: {
                driver: 'flat',
                target: 'eloquent',
                counts: {},
                switch_url: '/cp/webhook-manager/settings/storage',
            },
        },
        keys: ['driver'],
    },
};

describe('every key the server rejects reaches the screen', () => {
    for (const [name, { component, props, keys }] of Object.entries(pages)) {
        for (const key of keys) {
            it(`${name} shows the error for "${key}"`, () => {
                const message = `SERVER-SAID-${key}`;
                const wrapper = mountWithErrors(component, props, { [key]: message });

                expect(
                    visibleMessages(wrapper).some((text) => text.includes(message)),
                    `"${key}" was rejected by the server but its message is nowhere in the rendered page`,
                ).toBe(true);
            });
        }
    }
});

describe('the collected banner is styled as the error it reports', () => {
    for (const [name, { component, props, keys }] of Object.entries(pages)) {
        it(`${name} uses a variant Alert actually knows`, () => {
            const wrapper = mountWithErrors(component, props, { [keys[0]]: 'nope' });
            const banner = wrapper.find('[data-webhook-form-errors]');

            expect(banner.exists(), `${name} renders no collected banner`).toBe(true);

            // Alert's variants are default | warning | error | success. This
            // page family shipped `variant="danger"` (inbound) and
            // `type="error"` (templates) — neither is a prop it honours, so
            // the banner appeared in the neutral style: an error that did not
            // look like one.
            expect(banner.attributes('data-attr-variant')).toBe('error');
        });
    }
});

describe('a rejection outside the form itself is not swallowed', () => {
    // useForm's bag only carries what `form.submit()` sent. A refused delete
    // comes back through `router.delete`'s onError with its own bag, and
    // before this it had nowhere to go: the dialog closed, the record stayed,
    // and nothing on screen said why.
    const deletable = ['inbound/Edit', 'outbound/Edit', 'rules/Edit', 'templates/Edit'];

    for (const name of deletable) {
        it(`${name} shows what a refused delete said`, async () => {
            const { component, props } = pages[name];
            const wrapper = mount(component, { props });

            expect(wrapper.find('[data-webhook-form-errors]').exists()).toBe(false);

            const setup = wrapper.vm.$.setupState;
            expect(setup.actionErrors, `${name} has no state for a refused delete`).toBeTruthy();
            setup.actionErrors.value = { delete: 'This webhook is still referenced.' };
            await wrapper.vm.$nextTick();

            const banner = wrapper.find('[data-webhook-form-errors]');
            expect(banner.exists(), `${name} swallows a refused delete`).toBe(true);
            expect(banner.text()).toContain('This webhook is still referenced.');
        });
    }
});
