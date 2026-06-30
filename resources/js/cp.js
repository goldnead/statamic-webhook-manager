/**
 * Statamic Webhook Manager — Control Panel entry.
 *
 * Statamic 6 mounts an Inertia.js + Vue 3 SPA. Addons hook into the
 * page resolver via Statamic.$inertia.register(). Each registered name
 * must match the first argument passed to Inertia::render() in PHP.
 */

import OverviewIndex from './pages/overview/Index.vue';
import OutboundIndex from './pages/outbound/Index.vue';
import OutboundEdit from './pages/outbound/Edit.vue';
import DeliveriesIndex from './pages/deliveries/Index.vue';
import DeliveriesShow from './pages/deliveries/Show.vue';
import LogsIndex from './pages/logs/Index.vue';
import SettingsIndex from './pages/settings/Index.vue';
import DebugIndex from './pages/debug/Index.vue';
import InboundIndex from './pages/inbound/Index.vue';
import InboundEdit from './pages/inbound/Edit.vue';
import RulesIndex from './pages/rules/Index.vue';
import RulesEdit from './pages/rules/Edit.vue';
import TemplatesIndex from './pages/templates/Index.vue';
import TemplatesEdit from './pages/templates/Edit.vue';
import IntegrationsIndex from './pages/integrations/Index.vue';
import IntegrationsSetup from './pages/integrations/Setup.vue';
import InsightsIndex from './pages/insights/Index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('webhook-manager::Overview/Index', OverviewIndex);
    Statamic.$inertia.register('webhook-manager::Outbound/Index', OutboundIndex);
    Statamic.$inertia.register('webhook-manager::Outbound/Edit', OutboundEdit);
    Statamic.$inertia.register('webhook-manager::Deliveries/Index', DeliveriesIndex);
    Statamic.$inertia.register('webhook-manager::Deliveries/Show', DeliveriesShow);
    Statamic.$inertia.register('webhook-manager::Logs/Index', LogsIndex);
    Statamic.$inertia.register('webhook-manager::Settings/Index', SettingsIndex);
    Statamic.$inertia.register('webhook-manager::Debug/Index', DebugIndex);
    Statamic.$inertia.register('webhook-manager::Inbound/Index', InboundIndex);
    Statamic.$inertia.register('webhook-manager::Inbound/Edit', InboundEdit);
    Statamic.$inertia.register('webhook-manager::Rules/Index', RulesIndex);
    Statamic.$inertia.register('webhook-manager::Rules/Edit', RulesEdit);
    Statamic.$inertia.register('webhook-manager::Templates/Index', TemplatesIndex);
    Statamic.$inertia.register('webhook-manager::Templates/Edit', TemplatesEdit);
    Statamic.$inertia.register('webhook-manager::Integrations/Index', IntegrationsIndex);
    Statamic.$inertia.register('webhook-manager::Integrations/Setup', IntegrationsSetup);
    Statamic.$inertia.register('webhook-manager::Insights/Index', InsightsIndex);
});
