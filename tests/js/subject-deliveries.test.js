import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import SubjectDeliveries from '../../resources/js/components/SubjectDeliveries.vue';

/**
 * The delivery panel another addon embeds on its own page.
 *
 * It is registered globally and mounted by code this package does not own,
 * so the contract is the rendered result for each state the endpoint can
 * answer with: rows, no rows, and the replay affordance that must only show
 * where the server said the row can be replayed. A panel that offered replay
 * on a successful delivery would invite a duplicate send from a screen that
 * does not even belong to this addon.
 */

vi.mock('axios', () => ({
    default: { get: vi.fn() },
}));

function row(overrides = {}) {
    return {
        id: 1,
        status: 'success',
        method: 'POST',
        url: 'https://receiver.example.test/hook',
        response_code: 200,
        created_at: '2026-09-01T10:00:00+00:00',
        show_url: '/cp/webhook-manager/deliveries/1',
        can_replay: false,
        replay_url: null,
        ...overrides,
    };
}

function mountPanel(props = {}) {
    return mount(SubjectDeliveries, {
        props: {
            subjectType: 'payment',
            subjectId: 77,
            url: '/cp/webhook-manager/deliveries/for-subject',
            ...props,
        },
    });
}

describe('SubjectDeliveries', () => {
    beforeEach(() => {
        axios.get.mockReset();
    });

    it('asks the endpoint for the subject it was given', async () => {
        axios.get.mockResolvedValue({ data: { data: [], total: 0 } });

        mountPanel({ limit: 5 });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/cp/webhook-manager/deliveries/for-subject', {
            params: { subject_type: 'payment', subject_id: '77', limit: 5 },
        });
    });

    it('renders one row per delivery', async () => {
        axios.get.mockResolvedValue({
            data: {
                data: [row({ id: 1 }), row({ id: 2, status: 'failed', response_code: 500 })],
                total: 2,
            },
        });

        const wrapper = mountPanel();
        await flushPromises();

        expect(wrapper.findAll('[data-testid="subject-delivery-row"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="subject-deliveries-empty"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('500');
    });

    it('shows the empty state when nothing was recorded', async () => {
        axios.get.mockResolvedValue({ data: { data: [], total: 0 } });

        const wrapper = mountPanel();
        await flushPromises();

        expect(wrapper.find('[data-testid="subject-deliveries-empty"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('webhook-manager::messages.subject_deliveries_empty');
        expect(wrapper.findAll('[data-testid="subject-delivery-row"]')).toHaveLength(0);
    });

    it('offers replay only where the server allows it', async () => {
        axios.get.mockResolvedValue({
            data: {
                data: [
                    row({ id: 1, can_replay: false }),
                    row({ id: 2, status: 'failed', can_replay: true, replay_url: '/cp/webhook-manager/deliveries/2/replay' }),
                ],
                total: 2,
            },
        });

        const wrapper = mountPanel();
        await flushPromises();

        const rows = wrapper.findAll('[data-testid="subject-delivery-row"]');
        expect(rows[0].find('[data-testid="subject-delivery-replay"]').exists()).toBe(false);
        expect(rows[1].find('[data-testid="subject-delivery-replay"]').exists()).toBe(true);
    });

    it('shows the error state when the endpoint refuses', async () => {
        axios.get.mockRejectedValue({ response: { data: { message: 'Forbidden.' } } });

        const wrapper = mountPanel();
        await flushPromises();

        expect(wrapper.find('[data-testid="subject-deliveries-error"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Forbidden.');
    });
});
