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
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch } from '../api/client';
import Card from '../shared/ui/Card.vue';
import FeatureFlaggedEmpty from '../shared/ui/FeatureFlaggedEmpty.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const loading = ref(true);
const loadError = ref('');
const flagUnavailable = ref(false);
const alerts = ref([]);
let loadSeq = 0;

onMounted(async () => {
    await loadAlerts();
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
