<template>
    <div data-testid="safeguarding-context-page">
        <FeatureFlaggedEmpty v-if="flagUnavailable" />

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text">Safeguarding context</h1>
                <p class="mt-1 text-body text-text-muted">
                    Presence and severity category only. This is context for documentation,
                    not a safeguarding or child-protection case system.
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="safeguarding-context-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <div v-if="loading" class="mt-6 space-y-3" data-testid="safeguarding-context-loading">
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <Card
                v-else-if="loadError"
                class="mt-6"
                data-testid="safeguarding-context-error"
            >
                <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            </Card>

            <Card
                v-else-if="signals.length === 0"
                class="mt-6"
                data-testid="safeguarding-context-empty"
            >
                <p class="text-body text-text-muted">No safeguarding context signals.</p>
            </Card>

            <ul
                v-else
                class="mt-6 space-y-3"
                data-testid="safeguarding-context-list"
            >
                <li
                    v-for="signal in signals"
                    :key="signal.id"
                    class="rounded-md border border-border bg-surface px-4 py-3"
                    data-testid="safeguarding-context-item"
                >
                    <p class="text-body font-medium text-text">
                        {{ signal.given_name }} {{ signal.family_name }}
                    </p>
                    <p class="mt-1 text-body text-text-muted" data-testid="safeguarding-context-category">
                        {{ categoryLabel(signal) }}
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
const signals = ref([]);
let loadSeq = 0;

onMounted(async () => {
    await loadSignals();
});

async function loadSignals() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    flagUnavailable.value = false;

    try {
        const response = await apiFetch('/api/v1/safeguarding-signals');

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
            loadError.value = 'Unable to load safeguarding context.';

            return;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return;
        }

        signals.value = Array.isArray(payload.data)
            ? payload.data.filter(isRecord).map(normaliseSignal).filter((row) => row.id !== '')
            : [];
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load safeguarding context.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {Record<string, unknown>} row
 */
function normaliseSignal(row) {
    return {
        id: typeof row.id === 'string' ? row.id.trim() : '',
        pupil_id: typeof row.pupil_id === 'string' ? row.pupil_id : '',
        given_name: typeof row.given_name === 'string' ? row.given_name : '',
        family_name: typeof row.family_name === 'string' ? row.family_name : '',
        present: Boolean(row.present),
        severity: typeof row.severity === 'string' ? row.severity : '',
    };
}

/**
 * @param {{ present: boolean, severity: string }} signal
 */
function categoryLabel(signal) {
    if (! signal.present) {
        return 'Not present';
    }

    const labels = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
    };

    return `Present · ${labels[signal.severity] ?? signal.severity}`;
}

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}
</script>
