/**
 * Rows for the grouped trigger picker.
 *
 * Core's Combobox has no option groups, so each group's heading travels as a
 * row of its own (`heading: true`, value `__group:<type>`). The picker never
 * lets such a row become the value; see TriggerSelect.vue.
 *
 * `choices` comes from TriggerRegistry::groupedOptions(), already sorted by
 * heading and label. The search matches every word of the query against the
 * trigger's label, its group heading and its handle, so "zahlungen" lists the
 * whole payments group and "entry.del" finds the handle an operator copied
 * from a log. A heading stays only while one of its triggers is left.
 */
export const GROUP_PREFIX = '__group:';

export function isGroupRow(value) {
    return typeof value === 'string' && value.startsWith(GROUP_PREFIX);
}

function matches(choice, words) {
    if (!words.length) return true;

    const haystack = [choice.label, choice.group_label, choice.value]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return words.every((word) => haystack.includes(word));
}

export function buildTriggerRows(choices, query = '') {
    const words = String(query ?? '')
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean);

    const rows = [];
    let currentGroup = null;

    for (const choice of choices ?? []) {
        if (!matches(choice, words)) continue;

        if (choice.group && choice.group !== currentGroup) {
            currentGroup = choice.group;
            rows.push({
                value: GROUP_PREFIX + choice.group,
                label: choice.group_label || choice.group,
                heading: true,
            });
        }

        rows.push({ ...choice, heading: false });
    }

    return rows;
}
