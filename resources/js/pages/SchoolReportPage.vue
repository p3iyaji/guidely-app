<template>
    <div data-testid="school-report-page">
        <div>
            <h1 class="text-heading font-semibold text-text">School Report</h1>
            <p class="mt-1 text-body text-text-muted">
                SEND documentation readiness for Pupils in accessible Schools.
                Figures are documentation status only — not attendance, budget, or curriculum.
            </p>
        </div>

        <div
            class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
            data-testid="school-report-disclaimer"
            role="note"
        >
            Documentation evaluations support professional judgement. They are not diagnoses,
            funding decisions, or statutory determinations.
        </div>

        <div
            class="mt-4 flex flex-wrap gap-2"
            role="group"
            aria-label="Due window"
            data-testid="school-report-windows"
        >
            <button
                v-for="days in windowOptions"
                :key="days"
                type="button"
                class="rounded-full px-3 py-1 text-label font-medium focus:outline-none focus:ring-2 focus:ring-focus-ring"
                :class="windowDays === days
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-surface-muted text-text'"
                :aria-pressed="windowDays === days"
                :data-testid="`school-report-window-${days}`"
                @click="setWindow(days)"
            >
                {{ days }} days
            </button>
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="school-report-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="school-report-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <template v-else>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="school-report-kpis">
                <KpiCard
                    v-for="kpi in kpis"
                    :key="kpi.key"
                    :label="kpi.label"
                    :value="kpi.value"
                    interactive
                    :selected="drillKey === kpi.key"
                    @select="selectDrill(kpi.key)"
                />
            </div>

            <Card class="mt-6" data-testid="school-report-status-table">
                <h2 class="text-body font-semibold text-text">Documentation Status</h2>
                <p class="mt-1 text-meta text-text-muted">
                    Evaluating is in flight and is not counted as Ready.
                </p>
                <table class="mt-4 min-w-full divide-y divide-border text-left text-body">
                    <thead>
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium text-text">Status</th>
                            <th scope="col" class="px-3 py-2 font-medium text-text">Pupils</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="row in statusRows"
                            :key="row.status"
                            data-testid="school-report-status-row"
                            :data-status="row.status"
                        >
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-md text-left focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    :class="drillKey === row.status ? 'ring-2 ring-focus-ring' : ''"
                                    :aria-pressed="drillKey === row.status"
                                    :data-testid="`school-report-status-${row.status}`"
                                    @click="selectDrill(row.status)"
                                >
                                    <StatusPill :status="row.status" />
                                    <span
                                        v-if="row.status === 'evaluating'"
                                        class="text-meta text-text-muted"
                                    >
                                        In flight
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2 text-text" data-testid="school-report-status-count">
                                {{ row.count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card
                v-if="pupilsInScope === 0"
                class="mt-6"
                data-testid="school-report-empty"
            >
                <p class="text-body text-text" data-testid="school-report-empty-copy">
                    No Pilot data yet.
                </p>
            </Card>

            <Card
                v-if="drillKey !== null"
                class="mt-6"
                data-testid="school-report-drill"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-body font-semibold text-text">{{ drillHeading }}</h2>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-label font-medium text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="school-report-drill-clear"
                        @click="clearDrill"
                    >
                        Clear
                    </button>
                </div>
                <ul
                    v-if="drillItems.length > 0"
                    class="mt-4 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                    data-testid="school-report-drill-list"
                >
                    <li
                        v-for="item in drillItems"
                        :key="item.id"
                        class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        data-testid="school-report-drill-row"
                    >
                        <p class="text-body font-medium text-text" data-testid="school-report-drill-name">
                            {{ item.name }}
                        </p>
                        <StatusPill
                            v-if="item.status"
                            :status="item.status"
                        />
                        <div
                            v-else
                            class="flex flex-col gap-1 sm:items-end"
                        >
                            <span
                                class="text-meta text-text-muted"
                                data-testid="school-report-drill-type"
                            >
                                {{ item.typeLabel }}
                            </span>
                            <span
                                class="text-meta text-text-muted"
                                data-testid="school-report-drill-due-on"
                            >
                                {{ item.dueOn }}
                            </span>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-2 text-body text-text-muted"
                    data-testid="school-report-drill-empty"
                >
                    No matching Pupils or Review Cycles in this view.
                </p>
            </Card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import Card from '../shared/ui/Card.vue';
import KpiCard from '../shared/ui/KpiCard.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import StatusPill from '../shared/ui/StatusPill.vue';

const windowOptions = [7, 30, 90];
const statusOrder = ['ready', 'gaps', 'uncovered', 'not-started', 'evaluating'];
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const loadError = ref('');
const drillKey = ref(null);
const windowDays = ref(parseWindow(route.query.window));
const report = ref(emptyReport());
let loadSeq = 0;

const pupilsInScope = computed(() => report.value.pupils_in_scope);
const kpis = computed(() => [
    { key: 'in-scope', label: 'Pupils in scope', value: report.value.pupils_in_scope },
    { key: 'ready', label: 'Ready', value: report.value.ready },
    { key: 'gaps', label: 'Gaps', value: report.value.gaps },
    { key: 'cycles-due', label: 'Review Cycles due', value: report.value.review_cycles_due },
]);

const statusRows = computed(() => statusOrder.map((status) => ({
    status,
    count: report.value.by_status[status] ?? 0,
})));

const drillHeading = computed(() => {
    const headings = {
        'in-scope': 'Pupils in scope',
        ready: 'Ready',
        gaps: 'Gaps',
        uncovered: 'Uncovered',
        'not-started': 'Not started',
        evaluating: 'Evaluating (in flight)',
        'cycles-due': 'Review Cycles due',
    };

    return headings[drillKey.value] ?? 'Drill';
});

const drillItems = computed(() => {
    if (drillKey.value === 'cycles-due') {
        return report.value.cycles_due.map((cycle) => ({
            id: String(cycle.id ?? ''),
            name: pupilName(cycle),
            dueOn: formatDueOn(cycle.due_on),
            typeLabel: typeof cycle.type_label === 'string' ? cycle.type_label : '',
            status: null,
        }));
    }

    const pupils = report.value.pupils.filter((pupil) => {
        if (drillKey.value === 'in-scope' || drillKey.value === null) {
            return true;
        }

        return pupil.documentation_status === drillKey.value;
    });

    return pupils.map((pupil) => ({
        id: String(pupil.id ?? ''),
        name: pupilName(pupil),
        dueOn: '',
        typeLabel: '',
        status: pupil.documentation_status,
    }));
});

onMounted(async () => {
    await loadReport();
});

watch(
    () => [route.query.window, route.query.school_id],
    async () => {
        windowDays.value = parseWindow(route.query.window);
        await loadReport();
    },
);

/**
 * @param {string} key
 */
function selectDrill(key) {
    drillKey.value = key;
}

function clearDrill() {
    drillKey.value = null;
}

/**
 * @param {number} days
 */
function setWindow(days) {
    windowDays.value = days;
    const schoolId = schoolIdFromQuery();
    router.replace({
        path: '/school-report',
        query: {
            window: String(days),
            ...(schoolId ? { school_id: schoolId } : {}),
        },
    });
}

async function loadReport() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';

    const params = new URLSearchParams();
    params.set('window', String(windowDays.value));
    const schoolId = schoolIdFromQuery();

    if (schoolId) {
        params.set('school_id', schoolId);
    }

    try {
        const response = await apiFetch(`/api/v1/school-report?${params.toString()}`);

        if (seq !== loadSeq) {
            return;
        }

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load School Report.';

            return;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return;
        }

        report.value = normaliseReport(payload.data);
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load School Report.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {unknown} value
 */
function normaliseReport(value) {
    const record = isRecord(value) ? value : {};
    const byStatus = isRecord(record.by_status) ? record.by_status : {};

    return {
        pupils_in_scope: Number(record.pupils_in_scope ?? 0),
        ready: Number(record.ready ?? 0),
        gaps: Number(record.gaps ?? 0),
        review_cycles_due: Number(record.review_cycles_due ?? 0),
        window_days: Number(record.window_days ?? windowDays.value),
        by_status: {
            ready: Number(byStatus.ready ?? 0),
            gaps: Number(byStatus.gaps ?? 0),
            uncovered: Number(byStatus.uncovered ?? 0),
            'not-started': Number(byStatus['not-started'] ?? 0),
            evaluating: Number(byStatus.evaluating ?? 0),
        },
        pupils: asArray(record.pupils).filter(isRecord),
        cycles_due: asArray(record.cycles_due).filter(isRecord),
    };
}

function emptyReport() {
    return normaliseReport({});
}

/**
 * @param {unknown} pupil
 */
function pupilName(pupil) {
    if (!isRecord(pupil)) {
        return 'Pupil';
    }

    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
}

/**
 * @param {string|undefined} dueOn
 */
function formatDueOn(dueOn) {
    if (!dueOn) {
        return '—';
    }

    const parsed = new Date(`${dueOn}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return dueOn;
    }

    return parsed.toLocaleDateString('en-GB', {
        dateStyle: 'medium',
        timeZone: 'Europe/London',
    });
}

/**
 * @param {unknown} value
 */
function parseWindow(value) {
    const parsed = Number(value);

    return windowOptions.includes(parsed) ? parsed : 30;
}

function schoolIdFromQuery() {
    const raw = route.query.school_id;
    const schoolId = Array.isArray(raw) ? raw[0] : raw;

    if (typeof schoolId !== 'string') {
        return null;
    }

    const trimmed = schoolId.trim();

    return trimmed !== '' ? trimmed : null;
}

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {unknown} value
 */
function asArray(value) {
    return Array.isArray(value) ? value : [];
}
</script>
