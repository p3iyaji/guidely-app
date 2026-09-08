<template>
    <div data-testid="trust-dashboard-page">
        <FeatureFlaggedEmpty v-if="flagUnavailable" />

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text">Trust Dashboard</h1>
                <p class="mt-1 text-body text-text-muted">
                    Documentation Indicators across enabled Schools — Review Cycle lateness,
                    Gap density, Documentation Status mix, and Escalation Indicators. These are
                    documentation Indicators, not diagnoses.
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="trust-dashboard-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <div v-if="loading" class="mt-6 space-y-3" data-testid="trust-dashboard-loading">
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <Card
                v-else-if="loadError"
                class="mt-6"
                data-testid="trust-dashboard-error"
            >
                <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            </Card>

            <template v-else>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5" data-testid="trust-dashboard-kpis">
                    <KpiCard
                        v-for="kpi in kpis"
                        :key="kpi.key"
                        :label="kpi.label"
                        :value="kpi.value"
                    />
                </div>

                <Card class="mt-6" data-testid="trust-dashboard-status-table">
                    <h2 class="text-body font-semibold text-text">Documentation Status</h2>
                    <p class="mt-1 text-meta text-text-muted">
                        Status mix across enabled Schools. Evaluating is in flight and is not counted as Ready.
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
                                data-testid="trust-dashboard-status-row"
                                :data-status="row.status"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <StatusPill :status="row.status" />
                                        <span
                                            v-if="row.status === 'evaluating'"
                                            class="text-meta text-text-muted"
                                        >
                                            In flight
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-text" data-testid="trust-dashboard-status-count">
                                    {{ row.count }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>

                <Card class="mt-6" data-testid="trust-dashboard-escalations">
                    <h2 class="text-body font-semibold text-text">Escalation Indicators</h2>
                    <p class="mt-1 text-meta text-text-muted">
                        Flags cite encoded escalation Rules. They are not diagnoses or eligibility decisions.
                    </p>
                    <p
                        v-if="escalations.length === 0"
                        class="mt-4 text-body text-text-muted"
                        data-testid="trust-dashboard-escalations-empty"
                    >
                        No Escalation Indicators for enabled Schools.
                    </p>
                    <ul v-else class="mt-4 space-y-2" data-testid="trust-dashboard-escalations-list">
                        <li
                            v-for="flag in escalations"
                            :key="flag.rule_id"
                            class="text-body text-text"
                            data-testid="trust-dashboard-escalation-row"
                        >
                            <span class="font-medium">{{ flag.rule_code }}</span>
                            — {{ flag.rule_label }}
                            ({{ flag.pupil_count }})
                        </li>
                    </ul>
                </Card>

                <Card
                    v-if="benchmark"
                    class="mt-6"
                    data-testid="trust-dashboard-benchmark"
                >
                    <h2 class="text-body font-semibold text-text">Portfolio Indicators</h2>
                    <p class="mt-1 text-meta text-text-muted">
                        {{ benchmarkSchools.length > 0
                            ? 'Cross-School ranking and month-over-month Indicators. These are documentation Indicators, not diagnoses or Pupil prognosis.'
                            : 'Month-over-month Trust Indicators. These are documentation Indicators, not diagnoses or Pupil prognosis.' }}
                    </p>
                    <table
                        v-if="benchmarkSchools.length > 0"
                        class="mt-4 min-w-full divide-y divide-border text-left text-body"
                        data-testid="trust-dashboard-benchmark-ranks"
                    >
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Rank</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">School</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Gap density</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Lateness</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in benchmarkSchools"
                                :key="row.school_id"
                                data-testid="trust-dashboard-benchmark-rank-row"
                            >
                                <td class="px-3 py-2">{{ row.rank }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2">{{ formatPercent(row.gap_density) }}</td>
                                <td class="px-3 py-2">{{ formatPercent(row.lateness_rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="benchmarkTrends.length === 0"
                        class="mt-4 text-body text-text-muted"
                        data-testid="trust-dashboard-benchmark-trends-empty"
                    >
                        No trend history yet.
                    </p>
                    <ul v-else class="mt-4 space-y-2" data-testid="trust-dashboard-benchmark-trends">
                        <li
                            v-for="(point, index) in benchmarkTrends"
                            :key="`${point.month}-${index}`"
                            class="text-body text-text"
                            data-testid="trust-dashboard-benchmark-trend-row"
                        >
                            {{ point.month }}
                            — Gap density {{ formatPercent(point.gap_density) }}
                            <template v-if="point.gap_density_delta !== null">
                                ({{ formatDelta(point.gap_density_delta) }})
                            </template>
                            · Lateness {{ formatPercent(point.lateness_rate) }}
                            <template v-if="point.lateness_rate_delta !== null">
                                ({{ formatDelta(point.lateness_rate_delta) }})
                            </template>
                        </li>
                    </ul>
                </Card>

                <Card
                    v-if="schools.length === 0 && pupilsInScope === 0"
                    class="mt-6"
                    data-testid="trust-dashboard-empty"
                >
                    <p class="text-body text-text" data-testid="trust-dashboard-empty-copy">
                        No Pilot data yet.
                    </p>
                </Card>

                <Card
                    v-if="schools.length > 0"
                    class="mt-6 overflow-x-auto"
                    data-testid="trust-dashboard-schools"
                >
                    <h2 class="text-body font-semibold text-text">Enabled Schools</h2>
                    <p class="mt-1 text-meta text-text-muted">
                        Open a School Report for one enabled School. Pupil narratives stay on that School surface.
                    </p>
                    <table class="mt-4 min-w-full divide-y divide-border text-left text-body">
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-2 font-medium text-text">School</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Pupils</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Ready</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Gap density</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Lateness</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Escalation Indicators</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="school in schools"
                                :key="school.school_id"
                                data-testid="trust-dashboard-school-row"
                            >
                                <td class="px-3 py-2">
                                    <router-link
                                        class="font-medium text-text underline decoration-border underline-offset-2 focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                        :to="{ path: '/school-report', query: { school_id: school.school_id } }"
                                        :data-testid="`trust-dashboard-school-link-${school.school_id}`"
                                    >
                                        {{ school.name }}
                                    </router-link>
                                </td>
                                <td class="px-3 py-2 text-text">{{ school.pupils_in_scope }}</td>
                                <td class="px-3 py-2 text-text">{{ school.by_status.ready }}</td>
                                <td class="px-3 py-2 text-text">
                                    {{ formatRate(school.gap_density, school.gaps, school.pupils_in_scope) }}
                                </td>
                                <td class="px-3 py-2 text-text">
                                    {{ formatRate(school.lateness_rate, school.overdue_open_cycles, school.open_cycles) }}
                                </td>
                                <td class="px-3 py-2 text-text" data-testid="trust-dashboard-school-escalations">
                                    {{ school.escalated_pupils }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiFetch } from '../api/client';
import Card from '../shared/ui/Card.vue';
import FeatureFlaggedEmpty from '../shared/ui/FeatureFlaggedEmpty.vue';
import KpiCard from '../shared/ui/KpiCard.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import StatusPill from '../shared/ui/StatusPill.vue';

const statusOrder = ['ready', 'gaps', 'uncovered', 'not-started', 'evaluating'];

const loading = ref(true);
const loadError = ref('');
const flagUnavailable = ref(false);
const indicators = ref(emptyIndicators());
let loadSeq = 0;

const pupilsInScope = computed(() => indicators.value.pupils_in_scope);
const schools = computed(() => indicators.value.schools);
const escalations = computed(() => indicators.value.escalations);
const benchmark = computed(() => indicators.value.benchmark);
const benchmarkSchools = computed(() => benchmark.value?.schools ?? []);
const benchmarkTrends = computed(() => benchmark.value?.trends ?? []);

const kpis = computed(() => [
    {
        key: 'lateness',
        label: 'Lateness',
        value: formatRate(
            indicators.value.lateness_rate,
            indicators.value.overdue_open_cycles,
            indicators.value.open_cycles,
        ),
    },
    {
        key: 'gap-density',
        label: 'Gap density',
        value: formatRate(
            indicators.value.gap_density,
            indicators.value.gaps,
            indicators.value.pupils_in_scope,
        ),
    },
    { key: 'in-scope', label: 'Pupils in scope', value: indicators.value.pupils_in_scope },
    { key: 'ready', label: 'Ready', value: indicators.value.by_status.ready },
    { key: 'flagged-schools', label: 'Flagged Schools', value: indicators.value.flagged_schools },
]);

const statusRows = computed(() => statusOrder.map((status) => ({
    status,
    count: indicators.value.by_status[status] ?? 0,
})));

onMounted(async () => {
    await loadIndicators();
});

async function loadIndicators() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    flagUnavailable.value = false;

    try {
        const response = await apiFetch('/api/v1/trust-dashboard');

        if (seq !== loadSeq) {
            return;
        }

        if (response.status === 403) {
            const payload = await response.clone().json().catch(() => ({}));

            if (isRecord(payload) && payload.code === 'feature_not_available') {
                flagUnavailable.value = true;

                return;
            }

            loadError.value = 'You don’t have access.';

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Trust Dashboard.';

            return;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return;
        }

        indicators.value = normaliseIndicators(isRecord(payload) ? payload.data : null);
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load Trust Dashboard.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {unknown} value
 */
function normaliseIndicators(value) {
    const record = isRecord(value) ? value : {};
    const byStatus = isRecord(record.by_status) ? record.by_status : {};

    return {
        pupils_in_scope: Number(record.pupils_in_scope ?? 0),
        gaps: Number(record.gaps ?? 0),
        gap_density: Number(record.gap_density ?? 0),
        lateness_rate: Number(record.lateness_rate ?? 0),
        overdue_open_cycles: Number(record.overdue_open_cycles ?? 0),
        open_cycles: Number(record.open_cycles ?? 0),
        by_status: {
            ready: Number(byStatus.ready ?? 0),
            gaps: Number(byStatus.gaps ?? 0),
            uncovered: Number(byStatus.uncovered ?? 0),
            'not-started': Number(byStatus['not-started'] ?? 0),
            evaluating: Number(byStatus.evaluating ?? 0),
        },
        escalated_pupils: Number(record.escalated_pupils ?? 0),
        flagged_schools: Number(record.flagged_schools ?? 0),
        escalations: Array.isArray(record.escalations)
            ? record.escalations.filter(isRecord).map(normaliseEscalation).filter((flag) => flag.rule_id !== '')
            : [],
        schools: Array.isArray(record.schools)
            ? record.schools.filter(isRecord).map(normaliseSchool).filter((school) => school.school_id !== '')
            : [],
        benchmark: isRecord(record.benchmark) ? normaliseBenchmark(record.benchmark) : null,
    };
}

/**
 * @param {Record<string, unknown>} school
 */
function normaliseSchool(school) {
    const byStatus = isRecord(school.by_status) ? school.by_status : {};
    const schoolId = typeof school.school_id === 'string' ? school.school_id.trim() : '';

    return {
        school_id: schoolId,
        name: typeof school.name === 'string' ? school.name : 'School',
        pupils_in_scope: Number(school.pupils_in_scope ?? 0),
        gaps: Number(school.gaps ?? 0),
        gap_density: Number(school.gap_density ?? 0),
        lateness_rate: Number(school.lateness_rate ?? 0),
        overdue_open_cycles: Number(school.overdue_open_cycles ?? 0),
        open_cycles: Number(school.open_cycles ?? 0),
        by_status: {
            ready: Number(byStatus.ready ?? 0),
            gaps: Number(byStatus.gaps ?? 0),
            uncovered: Number(byStatus.uncovered ?? 0),
            'not-started': Number(byStatus['not-started'] ?? 0),
            evaluating: Number(byStatus.evaluating ?? 0),
        },
        escalated_pupils: Number(school.escalated_pupils ?? 0),
        flagged_schools: Number(school.flagged_schools ?? 0),
        escalations: Array.isArray(school.escalations)
            ? school.escalations.filter(isRecord).map(normaliseEscalation).filter((flag) => flag.rule_id !== '')
            : [],
    };
}

/**
 * @param {Record<string, unknown>} flag
 */
function normaliseEscalation(flag) {
    const ruleId = typeof flag.rule_id === 'string' ? flag.rule_id.trim() : '';

    return {
        rule_id: ruleId,
        rule_code: typeof flag.rule_code === 'string' ? flag.rule_code : '',
        rule_label: typeof flag.rule_label === 'string' ? flag.rule_label : '',
        pupil_count: Number(flag.pupil_count ?? 0),
    };
}

/**
 * @param {Record<string, unknown>} value
 */
function normaliseBenchmark(value) {
    return {
        ranked_by: typeof value.ranked_by === 'string' ? value.ranked_by : 'gap_density',
        schools: Array.isArray(value.schools)
            ? value.schools.filter(isRecord).map(normaliseRankedSchool).filter((school) => school.school_id !== '')
            : [],
        trends: Array.isArray(value.trends)
            ? value.trends.filter(isRecord).map(normaliseTrend).filter((point) => point.month !== '')
            : [],
    };
}

/**
 * @param {Record<string, unknown>} school
 */
function normaliseRankedSchool(school) {
    const schoolId = typeof school.school_id === 'string' ? school.school_id.trim() : '';

    return {
        school_id: schoolId,
        name: typeof school.name === 'string' ? school.name : 'School',
        rank: Number(school.rank ?? 0),
        gap_density: Number(school.gap_density ?? 0),
        lateness_rate: Number(school.lateness_rate ?? 0),
        pupils_in_scope: Number(school.pupils_in_scope ?? 0),
    };
}

/**
 * @param {Record<string, unknown>} point
 */
function normaliseTrend(point) {
    return {
        month: typeof point.month === 'string' ? point.month : '',
        lateness_rate: Number(point.lateness_rate ?? 0),
        gap_density: Number(point.gap_density ?? 0),
        pupils_in_scope: Number(point.pupils_in_scope ?? 0),
        gaps: Number(point.gaps ?? 0),
        lateness_rate_delta: point.lateness_rate_delta == null ? null : Number(point.lateness_rate_delta),
        gap_density_delta: point.gap_density_delta == null ? null : Number(point.gap_density_delta),
    };
}

function emptyIndicators() {
    return normaliseIndicators({});
}

/**
 * @param {number} rate
 * @param {number} numerator
 * @param {number} denominator
 */
function formatRate(rate, numerator, denominator) {
    const percent = formatPercent(rate);

    if (Number(denominator) === 0) {
        return percent;
    }

    return `${percent} (${numerator} of ${denominator})`;
}

/**
 * @param {number} rate
 */
function formatPercent(rate) {
    return `${Math.round(Number(rate) * 100)}%`;
}

/**
 * @param {number} delta
 */
function formatDelta(delta) {
    const amount = Number(delta);

    if (! Number.isFinite(amount)) {
        return '';
    }

    const percent = `${Math.round(amount * 100)}`;

    if (amount > 0) {
        return `+${percent} pp`;
    }

    return `${percent} pp`;
}

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}
</script>
