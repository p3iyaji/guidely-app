<template>
    <div data-testid="home-dashboard">
        <section class="flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-1 text-label font-semibold uppercase tracking-wider text-primary">
                    {{ formattedDate }}
                </p>
                <h1 class="font-display text-display font-bold text-text">{{ title }}</h1>
                <p class="mt-1 text-body text-text-muted">{{ subtitle }}</p>
            </div>
            <a
                :href="primaryAction.to"
                class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-md bg-primary px-4 text-body font-semibold text-primary-foreground hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring sm:self-auto"
            >
                <AppIcon :name="primaryAction.icon" class="size-4.5" />
                {{ primaryAction.label }}
            </a>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="kpi-row">
            <KpiCard
                v-for="kpi in kpis"
                :key="kpi.label"
                :label="kpi.label"
                :value="kpi.value"
                :icon="kpi.icon"
                :detail="kpi.detail"
            />
        </div>
        <p
            v-if="loadError"
            class="mt-4 text-body text-danger"
            data-testid="dashboard-summary-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.7fr)]">
            <section class="overflow-hidden rounded-lg border border-border bg-surface">
                <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        <h2 class="text-body font-semibold text-text">Needs attention</h2>
                        <p class="mt-0.5 text-meta text-text-muted">Priority work across your scope</p>
                    </div>
                    <span
                        v-if="actionItems.length > 0"
                        class="rounded-full bg-primary-soft px-2.5 py-1 text-meta font-semibold text-primary"
                        data-testid="action-item-count"
                    >
                        {{ actionItems.length }} {{ actionItems.length === 1 ? 'item' : 'items' }}
                    </span>
                </header>
                <div
                    v-if="loadError"
                    class="flex min-h-52 flex-col items-center justify-center px-6 py-10 text-center"
                    data-testid="action-items-unavailable"
                >
                    <span class="grid size-11 place-items-center rounded-full bg-danger-soft text-danger">
                        <AppIcon name="alerts" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-body font-semibold text-text">Action queue unavailable</h3>
                    <p class="mt-2 max-w-md text-body text-text-muted">
                        Try again later or use the quick actions to continue your work.
                    </p>
                </div>
                <nav
                    v-else-if="actionItems.length > 0"
                    class="divide-y divide-border"
                    aria-label="Needs attention"
                    data-testid="action-items"
                >
                    <a
                        v-for="item in actionItems"
                        :key="`${item.type}:${item.id}`"
                        :href="item.href"
                        class="group flex min-h-18 items-center gap-3 px-5 py-3 hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring"
                        data-testid="action-item"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-md bg-primary-soft text-primary"
                        >
                            <AppIcon :name="actionItemIcon(item.type)" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-body font-semibold text-text group-hover:text-primary"
                                data-testid="action-item-title"
                            >
                                {{ item.title }}
                            </span>
                            <span class="block text-meta text-text-muted">{{ item.detail }}</span>
                        </span>
                        <span
                            class="rounded-full px-2.5 py-1 text-label font-semibold"
                            :class="actionPriorityClass(item.priority)"
                        >
                            {{ actionPriorityLabel(item.priority) }}
                        </span>
                        <span class="text-text-muted" aria-hidden="true">›</span>
                    </a>
                </nav>
                <div
                    v-else
                    class="flex min-h-52 flex-col items-center justify-center px-6 py-10 text-center"
                    data-testid="action-items-empty"
                >
                    <span class="grid size-11 place-items-center rounded-full bg-primary-soft text-primary">
                        <AppIcon name="alerts" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-body font-semibold text-text">Nothing needs attention right now</h3>
                    <p class="mt-2 max-w-md text-body text-text-muted">
                        You’re all caught up across the pupils and work currently in your scope.
                    </p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-border bg-surface">
                <header class="border-b border-border px-5 py-4">
                    <h2 class="text-body font-semibold text-text">Quick actions</h2>
                    <p class="mt-0.5 text-meta text-text-muted">Common tasks for {{ roleLabel }}</p>
                </header>
                <nav class="divide-y divide-border" aria-label="Dashboard quick actions">
                    <a
                        v-for="action in quickActions"
                        :key="action.to"
                        :href="action.to"
                        class="group flex min-h-16 items-center gap-3 px-5 py-3 hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring"
                    >
                        <span class="grid size-9 shrink-0 place-items-center rounded-md bg-primary-soft text-primary">
                            <AppIcon :name="action.icon" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-body font-semibold text-text group-hover:text-primary">
                                {{ action.label }}
                            </span>
                            <span class="block text-meta text-text-muted">{{ action.detail }}</span>
                        </span>
                        <span class="text-text-muted" aria-hidden="true">›</span>
                    </a>
                </nav>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import { listOfflineDrafts } from '../features/evidence/offlineDraftQueue';
import AppIcon from '../shared/ui/AppIcon.vue';
import KpiCard from '../shared/ui/KpiCard.vue';

const session = useSession();

const firstName = computed(() => session.user.value?.name?.trim().split(/\s+/)[0] ?? '');
const title = computed(() => {
    const role = session.role.value;

    if (role === 'tenant_admin') {
        return firstName.value ? `Welcome back, ${firstName.value}` : 'Tenant administration';
    }

    if (role === 'trust_send_lead' || role === 'trust_executive') {
        return 'Trust overview';
    }

    return firstName.value ? `Good morning, ${firstName.value}` : 'Dashboard';
});

const subtitle = computed(() => {
    if (session.role.value === 'tenant_admin') {
        return 'Manage access, schools, integrations, and tenant configuration.';
    }

    if (session.role.value === 'trust_send_lead' || session.role.value === 'trust_executive') {
        return 'What needs attention across the schools in your trust.';
    }

    return 'What needs attention across your pupils and review work.';
});

const roleLabel = computed(() => {
    const role = session.role.value;

    if (!role) {
        return 'your role';
    }

    if (role === 'senco') {
        return 'SENCO';
    }

    return role.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
});

const formattedDate = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(new Date());

const summary = ref({
    pupilsInScope: null,
    openGaps: null,
    reviewCyclesDue: null,
    drafts: null,
    windowDays: 30,
    actionItems: [],
});
const loadError = ref('');
const actionItems = computed(() => summary.value.actionItems);

const kpis = computed(() => [
    { label: 'Pupils in scope', value: summary.value.pupilsInScope, icon: 'pupils', detail: 'Current active pupils' },
    { label: 'Open gaps', value: summary.value.openGaps, icon: 'gaps', detail: 'Open SRE gaps' },
    {
        label: 'Review cycles due',
        value: summary.value.reviewCyclesDue,
        icon: 'reviews',
        detail: `Overdue and due within ${summary.value.windowDays} days`,
    },
    { label: 'Drafts', value: summary.value.drafts, icon: 'drafts', detail: 'Server and device drafts' },
]);

const actionSets = {
    teacher: [
        { label: 'Capture evidence', detail: 'Record an observation or response', to: '/capture', icon: 'capture' },
        { label: 'Open my pupils', detail: 'View your assigned pupil list', to: '/pupils', icon: 'pupils' },
        { label: 'Continue a draft', detail: 'Return to saved evidence', to: '/drafts', icon: 'drafts' },
    ],
    support_staff: [
        { label: 'Capture evidence', detail: 'Record an observation or response', to: '/capture', icon: 'capture' },
        { label: 'Open my pupils', detail: 'View your assigned pupil list', to: '/pupils', icon: 'pupils' },
        { label: 'Continue a draft', detail: 'Return to saved evidence', to: '/drafts', icon: 'drafts' },
    ],
    senco: [
        { label: 'Open pupils', detail: 'Review pupil documentation', to: '/pupils', icon: 'pupils' },
        { label: 'Review open gaps', detail: 'Prioritise missing evidence', to: '/gaps', icon: 'gaps' },
        { label: 'Open review cycles', detail: 'Continue scheduled reviews', to: '/review-cycles', icon: 'reviews' },
    ],
    school_leader: [
        { label: 'Open school report', detail: 'Review school-wide documentation', to: '/school-report', icon: 'oversight' },
        { label: 'Open review cycles', detail: 'View scheduled reviews', to: '/review-cycles', icon: 'reviews' },
        { label: 'Safeguarding context', detail: 'Review contextual signals', to: '/safeguarding-context', icon: 'access' },
    ],
    trust_send_lead: [
        { label: 'Open trust dashboard', detail: 'Review trust-wide indicators', to: '/trust-dashboard', icon: 'trust' },
        { label: 'Review schools', detail: 'Compare schools in scope', to: '/schools', icon: 'schools' },
        { label: 'Open alerts', detail: 'Prioritise compliance alerts', to: '/alerts', icon: 'alerts' },
    ],
    trust_executive: [
        { label: 'Open trust dashboard', detail: 'Review trust-wide indicators', to: '/trust-dashboard', icon: 'trust' },
        { label: 'Review schools', detail: 'Compare schools in scope', to: '/schools', icon: 'schools' },
        { label: 'Open alerts', detail: 'Prioritise compliance alerts', to: '/alerts', icon: 'alerts' },
    ],
    tenant_admin: [
        { label: 'Open pupils', detail: 'Review and manage pupil records', to: '/pupils', icon: 'pupils' },
        { label: 'Manage users', detail: 'Provision staff and access', to: '/users', icon: 'users' },
        { label: 'Manage schools', detail: 'Update tenant schools', to: '/schools', icon: 'schools' },
    ],
    platform_operator: [
        { label: 'Open pilot toolkit', detail: 'Manage pilot onboarding', to: '/pilot-toolkit', icon: 'configuration' },
        { label: 'Open settings', detail: 'Review operator preferences', to: '/settings', icon: 'settings' },
    ],
};

const defaultActions = [
    { label: 'Open settings', detail: 'Review your workspace preferences', to: '/settings', icon: 'settings' },
];

const quickActions = computed(() => actionSets[session.role.value] ?? defaultActions);
const primaryAction = computed(() => quickActions.value[0]);

onMounted(loadSummary);

/**
 * @param {unknown} value
 * @returns {number|null}
 */
function countOrNull(value) {
    return Number.isInteger(value) && value >= 0 ? value : null;
}

/**
 * @param {unknown} value
 * @returns {number}
 */
function reviewWindowOrDefault(value) {
    return [7, 30, 90].includes(value) ? value : 30;
}

/**
 * @param {unknown} value
 * @returns {Array<{type: string, id: string, title: string, detail: string, href: string, priority: string, date: string|null}>}
 */
function validActionItems(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    const types = new Set(['review_cycle', 'gap', 'draft']);
    const priorities = new Set(['overdue', 'due', 'gap', 'draft']);

    return value.filter((item) => (
        item
        && typeof item === 'object'
        && types.has(item.type)
        && typeof item.id === 'string'
        && typeof item.title === 'string'
        && typeof item.detail === 'string'
        && typeof item.href === 'string'
        && item.href.startsWith('/')
        && priorities.has(item.priority)
        && (item.date === null || typeof item.date === 'string')
    )).slice(0, 6);
}

function actionItemIcon(type) {
    return {
        review_cycle: 'reviews',
        gap: 'gaps',
        draft: 'drafts',
    }[type] ?? 'alerts';
}

function actionPriorityLabel(priority) {
    return {
        overdue: 'Overdue',
        due: 'Due',
        gap: 'Gap',
        draft: 'Draft',
    }[priority] ?? 'Action';
}

function actionPriorityClass(priority) {
    return priority === 'overdue'
        ? 'bg-danger-soft text-danger'
        : 'bg-primary-soft text-primary';
}

async function loadSummary() {
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/dashboard-summary', {
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            throw new Error('Dashboard summary request failed.');
        }

        const payload = await response.json();
        const data = payload?.data ?? {};
        const serverDrafts = countOrNull(data.drafts);

        summary.value = {
            pupilsInScope: countOrNull(data.pupils_in_scope),
            openGaps: countOrNull(data.open_gaps),
            reviewCyclesDue: countOrNull(data.review_cycles_due),
            drafts: serverDrafts,
            windowDays: reviewWindowOrDefault(data.window_days),
            actionItems: validActionItems(data.action_items),
        };

        if (serverDrafts === null) {
            return;
        }

        try {
            const localDrafts = await listOfflineDrafts();
            const newLocalDrafts = localDrafts.filter((draft) => !draft.serverDraftId);
            summary.value.drafts = serverDrafts + newLocalDrafts.length;
        } catch {
            // The server count remains valid when device storage is unavailable.
        }
    } catch {
        loadError.value = 'Unable to load dashboard summary.';
    }
}
</script>
