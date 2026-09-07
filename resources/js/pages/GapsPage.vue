<template>
    <div data-testid="gaps-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Gaps</h1>
            <p class="mt-1 text-body text-text-muted">
                Open Gaps derived from current Determinations. Open a Gap to review its Reasoning Pathway.
            </p>
        </div>

        <div
            class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
            data-testid="gaps-disclaimer"
            role="note"
        >
            Documentation evaluations support professional judgement. They are not diagnoses,
            funding decisions, or statutory determinations.
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="gaps-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="gaps-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <Card
            v-else-if="gaps.length === 0"
            class="mt-6"
            data-testid="gaps-empty"
        >
            <p class="text-body text-text" data-testid="gaps-empty-copy">
                No open Gaps right now.
            </p>
        </Card>

        <ul
            v-else
            class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
            data-testid="gaps-list"
        >
            <li
                v-for="gap in gaps"
                :key="gap.id"
                data-testid="gap-row"
            >
                <RouterLink
                    :to="gapLink(gap)"
                    class="flex flex-col gap-2 px-4 py-3 text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring sm:flex-row sm:items-center sm:justify-between"
                    :data-testid="`gap-row-link-${gap.id}`"
                >
                    <div class="min-w-0">
                        <p class="text-body font-medium text-text" data-testid="gap-pupil-name">
                            {{ pupilName(gap) }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="gap-dimension">
                            {{ gap.dimension ?? '—' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label font-medium"
                            :class="resultToneClass(gap.result)"
                            data-testid="gap-result"
                        >
                            <span aria-hidden="true">●</span>
                            {{ gap.result_label || humaniseResult(gap.result) }}
                        </span>
                    </div>
                </RouterLink>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch } from '../api/client';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const gaps = ref([]);
const loading = ref(true);
const loadError = ref('');

onMounted(async () => {
    await loadGaps();
});

async function loadGaps() {
    loading.value = true;
    loadError.value = '';
    gaps.value = [];

    try {
        const response = await apiFetch('/api/v1/gaps');

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';
            loading.value = false;

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Gaps.';
            loading.value = false;

            return;
        }

        const payload = await response.json();
        gaps.value = asArray(payload.data).filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Gaps.';
    } finally {
        loading.value = false;
    }
}

/**
 * @param {Record<string, unknown>} gap
 */
function gapLink(gap) {
    const pupilId = gap.pupil_id ?? gap.pupil?.id;
    const determinationId = gap.determination_id ?? gap.determination?.id;

    return {
        name: 'pupil-detail',
        params: { id: String(pupilId ?? '') },
        query: {
            focus: 'determination',
            ...(determinationId ? { determination: String(determinationId) } : {}),
            ...(gap.id ? { gap: String(gap.id) } : {}),
        },
    };
}

/**
 * @param {Record<string, unknown>} gap
 */
function pupilName(gap) {
    const pupil = isRecord(gap.pupil) ? gap.pupil : null;

    if (!pupil) {
        return 'Pupil';
    }

    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
}

/**
 * @param {string|undefined} result
 */
function resultToneClass(result) {
    if (result === 'met') {
        return 'bg-success-soft text-success';
    }

    if (result === 'uncovered') {
        return 'bg-danger-soft text-danger';
    }

    if (result === 'escalated' || result === 'review_required' || result === 'unmet') {
        return 'bg-warning-soft text-warning';
    }

    return 'bg-surface-muted text-text-muted';
}

/**
 * @param {string|undefined} result
 */
function humaniseResult(result) {
    const labels = {
        met: 'Met',
        unmet: 'Not met',
        insufficient: 'Insufficient',
        uncovered: 'Uncovered',
        escalated: 'Escalated',
        review_required: 'Review required',
    };

    return labels[result] ?? (result || '—');
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
