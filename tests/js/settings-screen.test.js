import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router } from '@statamic/cms/inertia';

import Show from '../../resources/js/pages/settings/Index.vue';

/**
 * The settings screen, after it stopped being a printout.
 *
 * It used to render `config/webhook-manager.php` as read-only rows and tell the
 * operator to go and edit a file on the server. The form that replaced it is
 * generated from the `groups` the server sends — the same definition the
 * validation and the boot-time config override read — so the tests here are
 * about the wiring between that definition and the request, not about any
 * particular setting.
 */

const groups = () => [
    {
        title: 'Modules',
        description: 'Which parts of the addon are active.',
        fields: [
            { key: 'features.inbound', type: 'boolean', label: 'Inbound endpoints', description: '', nullable: false },
        ],
    },
    {
        title: 'Retry defaults',
        description: 'What happens after a delivery fails.',
        fields: [
            {
                key: 'retry.strategy',
                type: 'select',
                label: 'Strategy',
                description: '',
                nullable: false,
                options: [
                    { value: 'none', label: 'No retry' },
                    { value: 'linear', label: 'Linear' },
                    { value: 'exponential', label: 'Exponential' },
                ],
            },
            { key: 'retry.max_attempts', type: 'integer', label: 'Maximum attempts', description: '', nullable: false, min: 1 },
        ],
    },
    {
        title: 'Delivery logging',
        description: 'What is blanked out before a delivery is written down.',
        fields: [
            { key: 'logging.mask_headers', type: 'list', label: 'Masked headers', description: '', nullable: false },
            { key: 'http.user_agent', type: 'string', label: 'User agent', description: '', nullable: false },
        ],
    },
];

const values = (overrides = {}) => ({
    'features.inbound': true,
    'retry.strategy': 'exponential',
    'retry.max_attempts': 3,
    'logging.mask_headers': ['authorization', 'cookie'],
    'http.user_agent': 'Statamic-Webhook-Manager/1.0',
    ...overrides,
});

function mountScreen(props = {}) {
    return mount(Show, {
        props: {
            groups: groups(),
            values: values(),
            updateUrl: '/cp/webhook-manager/settings',
            environment: [
                { label: 'Queue name', value: 'default', env: 'WEBHOOK_MANAGER_QUEUE_NAME' },
            ],
            rawConfig: '{}',
            configFilePath: '/app/config/webhook-manager.php',
            storage: {
                driver: 'eloquent',
                driver_label: 'Database',
                source: 'config',
                flat_path: '/app/content/webhooks',
                counts: {},
                target: 'flat',
                target_label: 'Flat file (YAML)',
                switch_url: '/cp/webhook-manager/settings/storage',
            },
            ...props,
        },
    });
}

/**
 * Press Save.
 *
 * Not `find('[data-settings-save]').trigger('click')`: the CP components are
 * stubs in this harness (tests/js/setup.js) and the stub mirrors attributes
 * into the DOM without binding listeners, so a click on the stubbed Button
 * reaches nothing and would pass no matter what `save()` does. The button's
 * presence is asserted separately — this calls what it is wired to.
 */
async function save(wrapper) {
    wrapper.vm.save();
    await flushPromises();
    await wrapper.vm.$nextTick();
}

/**
 * Stand in for the Inertia router's PATCH.
 *
 * The page mutates through `router.patch` rather than through axios, so what
 * a test can observe is the visit it makes and the callback it is answered
 * with. `outcome` is called with the options object the page passed, and
 * decides whether this visit succeeded or was rejected.
 */
function stubPatch(outcome) {
    return vi.spyOn(router, 'patch').mockImplementation((url, data, options) => {
        outcome(options ?? {});
        options?.onFinish?.();
    });
}

describe('the settings screen', () => {
    it('draws a control for every field the server defines, and none besides', () => {
        // The whole point of generating the form from `groups`: the screen
        // cannot show a field the validation does not know, and cannot omit
        // one it does. The read-only version this replaced kept its own list
        // of labels in JavaScript and could disagree with the server silently.
        const wrapper = mountScreen();

        const expected = groups().flatMap((g) => g.fields.map((f) => f.key));
        const rendered = wrapper.findAll('[data-settings-field]').map((el) => el.attributes('data-settings-field'));

        expect(rendered).toEqual(expected);
    });

    it('shows what the deployment owns without offering a control for it', () => {
        // Env-provided values are on the page so they can be checked, not so
        // they can be half-changed: a database row that outranks an env var
        // comes back on the next deploy.
        const wrapper = mountScreen();

        expect(wrapper.find('[data-settings-environment="WEBHOOK_MANAGER_QUEUE_NAME"]').exists()).toBe(true);
        expect(wrapper.find('[data-settings-field="queue.name"]').exists()).toBe(false);
    });

    it('sends every field, with a list split back into an array', () => {
        const wrapper = mountScreen();

        const sent = wrapper.vm.fromForm(wrapper.vm.form);

        expect(sent['retry.max_attempts']).toBe(3);
        expect(sent['logging.mask_headers']).toEqual(['authorization', 'cookie']);
    });

    it('drops the blank lines a textarea leaves behind', () => {
        // A list is edited as lines because that is how header names are read
        // and pasted. A trailing newline is what pasting always leaves, and an
        // empty string is not a header name.
        const wrapper = mountScreen();

        wrapper.vm.form['logging.mask_headers'] = 'authorization\n\n  cookie  \n';

        expect(wrapper.vm.fromForm(wrapper.vm.form)['logging.mask_headers'])
            .toEqual(['authorization', 'cookie']);
    });

    it('saves through the Inertia router, not through axios', async () => {
        // A page mutation on `axios` loses the progress bar, the flash toast,
        // the unsaved-changes guard and the back button — and, on this page in
        // particular, left the diagnostics panel's resolved config tree
        // showing the state from before the save. The whole page has to be
        // re-rendered, which is what a router visit does.
        const wrapper = mountScreen();
        wrapper.vm.form['retry.max_attempts'] = '7';

        const patch = stubPatch(({ onSuccess }) => onSuccess?.({}));

        await save(wrapper);

        expect(patch).toHaveBeenCalledTimes(1);

        const [url, payload] = patch.mock.calls[0];
        expect(url).toBe('/cp/webhook-manager/settings');
        expect(payload.settings['retry.max_attempts']).toBe('7');
    });

    it('takes the answer from the server, not what was typed', async () => {
        // An integer arrives from a text input as a string and comes back an
        // integer; a value equal to the shipped default comes back as that
        // default with the stored row deleted. The answer arrives as new page
        // props after the redirect, which is what the watcher picks up.
        const wrapper = mountScreen();
        wrapper.vm.form['retry.max_attempts'] = '7';

        stubPatch(({ onSuccess }) => onSuccess?.({}));
        await save(wrapper);

        await wrapper.setProps({ values: values({ 'retry.max_attempts': 5 }) });

        expect(wrapper.vm.form['retry.max_attempts']).toBe(5);
        expect(wrapper.vm.dirty).toBe(false);
    });

    it('puts a rejected field back next to its own control', async () => {
        const wrapper = mountScreen();

        stubPatch(({ onError }) => onError?.({ 'settings.retry.max_attempts': 'Must be at least 1.' }));

        await save(wrapper);

        expect(wrapper.find('[data-settings-field-error="retry.max_attempts"]').text())
            .toContain('Must be at least 1.');
    });

    it('says something when the save fails with no error bag at all', async () => {
        // A 500 or a dropped connection has no errors. Rendering an empty
        // banner is how a failed save looks exactly like a successful one,
        // which is the worst of the three outcomes.
        const wrapper = mountScreen();

        stubPatch(({ onError }) => onError?.({}));

        await save(wrapper);

        expect(wrapper.find('[data-settings-form-errors]').exists()).toBe(true);
    });

    it('surfaces an error that belongs to no control on the page', async () => {
        // There should be none — every validated key has a field here. A rule
        // added on the server and not here would otherwise be rejected in
        // silence, which reads as "nothing happened".
        const wrapper = mountScreen();

        stubPatch(({ onError }) => onError?.({ 'settings.something.new': 'Nope.' }));

        await save(wrapper);

        expect(wrapper.find('[data-settings-form-errors]').text()).toContain('Nope.');
    });

    it('reloads the form when a visit hands it new values', async () => {
        // A storage switch or a back button re-renders this page against the
        // same component instance. Without the watcher the form would keep
        // showing the values from the visit before it.
        const wrapper = mountScreen();

        await wrapper.setProps({ values: values({ 'retry.max_attempts': 42 }) });

        expect(wrapper.vm.form['retry.max_attempts']).toBe(42);
        expect(wrapper.vm.dirty).toBe(false);
    });

    it('knows when nothing has changed', async () => {
        const wrapper = mountScreen();

        expect(wrapper.vm.dirty).toBe(false);

        wrapper.vm.form['http.user_agent'] = 'Something/2.0';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.dirty).toBe(true);
    });

    it('offers a save button', () => {
        expect(mountScreen().find('[data-settings-save]').exists()).toBe(true);
    });
});
