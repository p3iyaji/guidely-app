<template>
    <div data-testid="alerts-page">
        <FeatureFlaggedEmpty v-if="flagUnavailable" />

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text">Alerts</h1>
                <p class="mt-1 text-body text-text-muted">
                    Open Indicator alerts when live Lateness or Gap density meets a configured threshold.
                    Citations name the Indicator and threshold only.
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="alerts-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <Card
                v-if="isThresholdAdmin"
                class="mt-6"
                data-testid="threshold-manager"
            >
                <div>
                    <h2 class="text-body font-semibold text-text">Alert thresholds</h2>
                    <p class="mt-1 text-meta text-text-muted">
                        Set the Trust-level percentage that opens each Indicator alert.
                    </p>
                </div>

                <div
                    v-if="thresholdLoading"
                    class="mt-4 space-y-3"
                    data-testid="thresholds-loading"
                >
                    <LoadingSkeleton variant="line" />
                    <LoadingSkeleton variant="line" />
                </div>

                <div v-else-if="thresholdLoadError" class="mt-4">
                    <p class="text-body text-danger" data-testid="thresholds-error" role="alert">
                        {{ thresholdLoadError }}
                    </p>
                    <ButtonSecondary class="mt-3" type="button" @click="loadThresholds">
                        Try again
                    </ButtonSecondary>
                </div>

                <form v-else class="mt-4 space-y-4" data-testid="threshold-form" @submit.prevent="saveThresholds">
                    <p
                        v-if="usingDefaults"
                        class="rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                        data-testid="thresholds-default"
                    >
                        No Trust thresholds have been saved yet. The current default is 50%.
                    </p>

                    <div
                        v-for="threshold in THRESHOLD_CONFIGS"
                        :key="threshold.metric"
                    >
                        <label
                            class="block text-body font-medium text-text"
                            :for="`threshold-${threshold.metric}`"
                        >
                            {{ threshold.label }}
                        </label>
                        <p
                            :id="`threshold-${threshold.metric}-help`"
                            class="mt-1 text-meta text-text-muted"
                        >
                            {{ threshold.description }}
                        </p>
                        <div class="mt-2 flex max-w-xs items-center gap-2">
                            <input
                                :id="`threshold-${threshold.metric}`"
                                v-model="thresholdForm[threshold.metric]"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                max="100"
                                step="0.1"
                                required
                                class="w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                :aria-invalid="fieldErrors[threshold.metric] ? 'true' : 'false'"
                                :aria-describedby="fieldErrors[threshold.metric]
                                    ? `threshold-${threshold.metric}-help threshold-${threshold.metric}-error`
                                    : `threshold-${threshold.metric}-help`"
                                :data-testid="`threshold-${threshold.metric}`"
                            >
                            <span class="text-body text-text-muted" aria-hidden="true">%</span>
                        </div>
                        <p
                            v-if="fieldErrors[threshold.metric]"
                            :id="`threshold-${threshold.metric}-error`"
                            class="mt-1 text-meta text-danger"
                            :data-testid="`threshold-error-${threshold.metric}`"
                        >
                            {{ fieldErrors[threshold.metric] }}
                        </p>
                    </div>

                    <p
                        v-if="thresholdSaveError"
                        class="text-body text-danger"
                        data-testid="thresholds-save-error"
                        role="alert"
                    >
                        {{ thresholdSaveError }}
                    </p>
                    <p
                        v-if="thresholdSuccess"
                        class="text-body text-success"
                        data-testid="thresholds-success"
                        role="status"
                    >
                        {{ thresholdSuccess }}
                    </p>

                    <ButtonPrimary type="submit" :disabled="thresholdSaving" data-testid="thresholds-save">
                        {{ thresholdSaving ? 'Saving…' : 'Save thresholds' }}
                    </ButtonPrimary>
                </form>
            </Card>

            <template v-if="canViewAlerts">
                <div v-if="loading" class="mt-6 space-y-3" data-testid="alerts-loading">
                    <LoadingSkeleton variant="line" />
                    <LoadingSkeleton variant="line" />
                    <LoadingSkeleton variant="card" />
                </div>

                <Card
                    v-else-if="loadError"
                    class="mt-6"
                    data-testid="alerts-error"
                >
                    <p class="text-body text-danger" role="alert">{{ loadError }}</p>
                </Card>

                <Card
                    v-else-if="alerts.length === 0"
                    class="mt-6"
                    data-testid="alerts-empty"
                >
                    <p class="text-body text-text-muted">No open Indicator alerts.</p>
                </Card>

                <ul
                    v-else
                    class="mt-6 space-y-3"
                    data-testid="alerts-list"
                >
                    <li
                        v-for="alert in alerts"
                        :key="alert.id"
                        class="rounded-md border border-border bg-surface px-4 py-3"
                        data-testid="alerts-item"
                    >
                        <p class="text-body font-medium text-text">
                            <router-link
                                v-if="alert.school_id"
                                class="underline decoration-border underline-offset-2 focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                :to="{ path: '/school-report', query: { school_id: alert.school_id } }"
                                :data-testid="`alerts-school-link-${alert.school_id}`"
                            >
                                {{ alert.school_name || 'School' }}
                            </router-link>
                            <span v-else data-testid="alerts-trust-scope">Trust</span>
                            <span class="font-normal text-text-muted"> · {{ alert.metric_label }}</span>
                        </p>
                        <p class="mt-1 text-body text-text" data-testid="alerts-citation">
                            {{ alert.citation }}
                        </p>
                    </li>
                </ul>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import FeatureFlaggedEmpty from '../shared/ui/FeatureFlaggedEmpty.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const THRESHOLD_CONFIGS = [
    {
        metric: 'gap_density',
        label: 'Gap density',
        description: 'Open an alert when the share of pupil records with documentation gaps reaches this percentage.',
    },
    {
        metric: 'lateness_rate',
        label: 'Lateness rate',
        description: 'Open an alert when the share of overdue open reviews reaches this percentage.',
    },
];
const ALERT_VIEWER_ROLES = ['senco', 'trust_send_lead', 'trust_executive'];
const DEFAULT_THRESHOLD_PERCENT = '50';

const session = useSession();
const isThresholdAdmin = computed(() => session.role.value === 'tenant_admin');
const canViewAlerts = computed(() => ALERT_VIEWER_ROLES.includes(session.role.value));
const loading = ref(true);
const loadError = ref('');
const flagUnavailable = ref(false);
const alerts = ref([]);
const thresholdLoading = ref(false);
const thresholdLoadError = ref('');
const thresholdSaving = ref(false);
const thresholdSaveError = ref('');
const thresholdSuccess = ref('');
const usingDefaults = ref(false);
const thresholdForm = reactive({
    gap_density: DEFAULT_THRESHOLD_PERCENT,
    lateness_rate: DEFAULT_THRESHOLD_PERCENT,
});
const fieldErrors = reactive({
    gap_density: '',
    lateness_rate: '',
});
let loadSeq = 0;

onMounted(async () => {
    const requests = [];

    if (canViewAlerts.value) {
        requests.push(loadAlerts());
    } else {
        loading.value = false;
    }

    if (isThresholdAdmin.value) {
        requests.push(loadThresholds());
    }

    await Promise.all(requests);
});

async function loadAlerts() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    flagUnavailable.value = false;

    try {
        const response = await apiFetch('/api/v1/compliance-alerts');

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
            loadError.value = 'Unable to load alerts.';

            return;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return;
        }

        alerts.value = Array.isArray(payload.data)
            ? payload.data.filter(isRecord).map(normaliseAlert).filter((alert) => alert.id !== '')
            : [];
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load alerts.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

async function loadThresholds() {
    thresholdLoading.value = true;
    thresholdLoadError.value = '';
    thresholdSaveError.value = '';
    thresholdSuccess.value = '';
    clearThresholdFieldErrors();

    try {
        const response = await apiFetch('/api/v1/compliance-alert-thresholds', {
            skipForbiddenRedirect: true,
        });
        const payload = await response.clone().json().catch(() => ({}));

        if (response.status === 403 && isRecord(payload) && payload.code === 'feature_not_available') {
            flagUnavailable.value = true;

            return;
        }

        if (!response.ok) {
            thresholdLoadError.value = response.status === 403
                ? 'You don’t have access.'
                : 'Unable to load alert thresholds.';

            return;
        }

        const rows = Array.isArray(payload.data) ? payload.data.filter(isRecord) : [];
        const trustThresholds = rows.filter((row) => row.school_id == null);
        usingDefaults.value = trustThresholds.length === 0;

        for (const threshold of THRESHOLD_CONFIGS) {
            const row = trustThresholds.find((candidate) => candidate.metric === threshold.metric);
            thresholdForm[threshold.metric] = row && isValidDecimalThreshold(row.threshold)
                ? formatPercent(Number(row.threshold) * 100)
                : DEFAULT_THRESHOLD_PERCENT;
        }
    } catch {
        thresholdLoadError.value = 'Unable to load alert thresholds.';
    } finally {
        thresholdLoading.value = false;
    }
}

async function saveThresholds() {
    clearThresholdFieldErrors();
    thresholdSaveError.value = '';
    thresholdSuccess.value = '';

    if (!validateThresholdForm()) {
        thresholdSaveError.value = 'Check the highlighted thresholds.';

        return;
    }

    thresholdSaving.value = true;

    try {
        for (const threshold of THRESHOLD_CONFIGS) {
            const response = await apiFetch('/api/v1/compliance-alert-thresholds', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    school_id: null,
                    metric: threshold.metric,
                    threshold: Number(thresholdForm[threshold.metric]) / 100,
                }),
                skipForbiddenRedirect: true,
            });
            const payload = await response.clone().json().catch(() => ({}));

            if (response.status === 403 && isRecord(payload) && payload.code === 'feature_not_available') {
                flagUnavailable.value = true;

                return;
            }

            if (!response.ok) {
                if (response.status === 422 && isRecord(payload)) {
                    applyThresholdFieldError(threshold.metric, payload);
                }

                thresholdSaveError.value = isRecord(payload) && typeof payload.message === 'string'
                    ? payload.message
                    : 'Unable to save alert thresholds.';

                return;
            }
        }

        usingDefaults.value = false;
        thresholdSuccess.value = 'Alert thresholds saved.';
    } catch {
        thresholdSaveError.value = 'Unable to save alert thresholds.';
    } finally {
        thresholdSaving.value = false;
    }
}

function clearThresholdFieldErrors() {
    for (const threshold of THRESHOLD_CONFIGS) {
        fieldErrors[threshold.metric] = '';
    }
}

function validateThresholdForm() {
    let valid = true;

    for (const threshold of THRESHOLD_CONFIGS) {
        const value = Number(thresholdForm[threshold.metric]);

        if (String(thresholdForm[threshold.metric]).trim() === '' || !Number.isFinite(value) || value < 0 || value > 100) {
            fieldErrors[threshold.metric] = 'Enter a percentage from 0 to 100.';
            valid = false;
        }
    }

    return valid;
}

/**
 * @param {string} metric
 * @param {Record<string, unknown>} payload
 */
function applyThresholdFieldError(metric, payload) {
    const errors = payload.errors;

    if (!isRecord(errors)) {
        return;
    }

    const messages = errors.threshold;
    fieldErrors[metric] = Array.isArray(messages)
        ? String(messages[0] ?? '')
        : String(messages ?? '');
}

/**
 * @param {unknown} value
 */
function isValidDecimalThreshold(value) {
    const threshold = Number(value);

    return Number.isFinite(threshold) && threshold >= 0 && threshold <= 1;
}

/**
 * @param {number} value
 */
function formatPercent(value) {
    return Number(value.toFixed(4)).toString();
}

/**
 * @param {Record<string, unknown>} row
 */
function normaliseAlert(row) {
    const schoolId = typeof row.school_id === 'string' ? row.school_id.trim() : '';

    return {
        id: typeof row.id === 'string' ? row.id.trim() : '',
        scope: typeof row.scope === 'string' ? row.scope : '',
        school_id: schoolId,
        school_name: typeof row.school_name === 'string' ? row.school_name : '',
        metric: typeof row.metric === 'string' ? row.metric : '',
        metric_label: typeof row.metric_label === 'string' ? row.metric_label : '',
        citation: typeof row.citation === 'string' ? row.citation : '',
    };
}

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}
</script>
