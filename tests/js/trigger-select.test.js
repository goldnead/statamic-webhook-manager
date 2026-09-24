import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { buildTriggerRows, isGroupRow } from '../../resources/js/support/triggerChoices.js';
import TriggerSelect from '../../resources/js/components/TriggerSelect.vue';
import OutboundEdit from '../../resources/js/pages/outbound/Edit.vue';
import RulesEdit from '../../resources/js/pages/rules/Edit.vue';

/**
 * The trigger picker with sixty triggers from seven addons.
 *
 * Core's Combobox has no option groups, so the headings travel as rows of
 * their own. What must hold: every group is introduced by its heading, a
 * search keeps a heading only while one of its triggers survives, a search
 * for the group name finds the whole group, and choosing a heading never
 * becomes the value.
 */

const choices = [
    { value: 'entry.saved', label: 'Eintrag: gespeichert', short_label: 'Gespeichert', group: 'entry', group_label: 'Einträge' },
    { value: 'entry.deleted', label: 'Eintrag: gelöscht', short_label: 'Gelöscht', group: 'entry', group_label: 'Einträge' },
    { value: 'payments.paid', label: 'Zahlungen: Zahlung eingegangen', short_label: 'Zahlung eingegangen', group: 'payments', group_label: 'Zahlungen' },
    { value: 'payments.refunded', label: 'Zahlungen: Zahlung erstattet', short_label: 'Zahlung erstattet', group: 'payments', group_label: 'Zahlungen' },
];

describe('buildTriggerRows', () => {
    it('puts a heading row before each group, in the order the server sent', () => {
        const rows = buildTriggerRows(choices, '');

        expect(rows.map((r) => r.value)).toEqual([
            '__group:entry', 'entry.saved', 'entry.deleted',
            '__group:payments', 'payments.paid', 'payments.refunded',
        ]);
        expect(rows[0].label).toBe('Einträge');
        expect(rows[0].heading).toBe(true);
        expect(isGroupRow(rows[0].value)).toBe(true);
        expect(isGroupRow('payments.paid')).toBe(false);
    });

    it('keeps a heading only while one of its triggers matches', () => {
        const rows = buildTriggerRows(choices, 'erstattet');

        expect(rows.map((r) => r.value)).toEqual(['__group:payments', 'payments.refunded']);
    });

    it('finds a whole group by its heading and a trigger by its handle', () => {
        expect(buildTriggerRows(choices, 'zahlungen').filter((r) => !r.heading)).toHaveLength(2);
        expect(buildTriggerRows(choices, 'entry.del').map((r) => r.value)).toEqual(['__group:entry', 'entry.deleted']);
    });

    it('matches every word of the query, in any order', () => {
        const rows = buildTriggerRows(choices, 'eingegangen zahlung');

        expect(rows.filter((r) => !r.heading).map((r) => r.value)).toEqual(['payments.paid']);
    });

    it('falls back to a flat list when the server sent no groups', () => {
        const rows = buildTriggerRows([{ value: 'a', label: 'A' }], '');

        expect(rows.map((r) => r.value)).toEqual(['a']);
    });
});

describe('TriggerSelect', () => {
    it('ignores a heading row picked from the list', async () => {
        const wrapper = mount(TriggerSelect, { props: { modelValue: null, choices } });

        wrapper.vm.onPicked('__group:payments');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();

        wrapper.vm.onPicked('payments.paid');
        expect(wrapper.emitted('update:modelValue')).toEqual([['payments.paid']]);
    });

    it('builds its choices from the flat options when no groups were sent', () => {
        const wrapper = mount(TriggerSelect, { props: { modelValue: null, options: { 'entry.saved': 'Entry: saved' } } });

        expect(wrapper.vm.rows.map((r) => r.value)).toEqual(['entry.saved']);
    });
});

describe('a new record starts without a trigger', () => {
    it('outbound: the picker is empty, not the first trigger of the list', () => {
        const wrapper = mount(OutboundEdit, {
            props: {
                webhook: { name: '', handle: '', enabled: true, headers: [] },
                triggerOptions: { 'affiliates.commission_earned': 'Partner: Provision verdient', 'entry.saved': 'Eintrag: gespeichert' },
                triggerChoices: choices,
                authOptions: { none: 'None' },
                isNew: true,
                canDelete: false,
                saveUrl: '/cp/webhook-manager/outbound',
                indexUrl: '/cp/webhook-manager/outbound',
            },
        });

        const picker = wrapper.findComponent(TriggerSelect);
        expect(picker.exists()).toBe(true);
        expect(picker.props('modelValue')).toBeNull();
    });

    it('rules: the picker is empty, not the first trigger of the list', () => {
        const wrapper = mount(RulesEdit, {
            props: {
                rule: { name: '', handle: '', enabled: true, conditions: [] },
                triggerOptions: { 'affiliates.commission_earned': 'Partner: Provision verdient' },
                triggerChoices: choices,
                actionOptions: { webhook: 'Send webhook' },
                isNew: true,
                canDelete: false,
                saveUrl: '/cp/webhook-manager/rules',
                indexUrl: '/cp/webhook-manager/rules',
            },
        });

        const picker = wrapper.findComponent(TriggerSelect);
        expect(picker.exists()).toBe(true);
        expect(picker.props('modelValue')).toBeNull();
    });
});
