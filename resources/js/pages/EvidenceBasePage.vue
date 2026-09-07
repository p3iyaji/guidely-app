<template>
    <div data-testid="evidence-base-page">
        <div v-if="loading" class="space-y-3" data-testid="evidence-base-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text" data-testid="evidence-base-title">
                    {{ pupilName }}
                </h1>
                <p
                    v-if="pupilSubtitle"
                    class="mt-1 text-body text-text-muted"
                    data-testid="evidence-base-subtitle"
                >
                    {{ pupilSubtitle }}
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="evidence-base-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <p
                v-if="loadError"
                class="mt-4 text-body text-danger"
                data-testid="evidence-base-error"
                role="alert"
            >
                {{ loadError }}
            </p>

            <div
                class="mt-6 flex flex-wrap gap-2"
                role="group"
                aria-label="Evidence filters"
                data-testid="evidence-base-filters"
            >
                <button
                    v-for="chip in filterChips"
                    :key="chip.value"
                    type="button"
                    class="min-h-11 rounded-md border px-3 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :class="activeFilter === chip.value
                        ? 'border-border bg-surface-muted font-semibold text-text'
                        : 'border-border bg-surface text-text-muted'"
                    :aria-pressed="activeFilter === chip.value ? 'true' : 'false'"
                    :data-testid="`evidence-filter-${chip.value || 'all'}`"
                    @click="setFilter(chip.value)"
                >
                    {{ chip.label }}
                </button>
            </div>

            <Card
                v-if="!loadError && records.length === 0"
                class="mt-6"
                data-testid="evidence-base-empty"
            >
                <p class="text-body text-text" data-testid="evidence-base-empty-copy">
                    {{ emptyCopy }}
                </p>
                <div
                    v-if="showCaptureCta"
                    class="mt-4"
                >
                    <ButtonPrimary
                        class="min-h-11"
                        data-testid="evidence-base-capture-cta"
                        @click="goToCapture"
                    >
                        Capture Evidence
                    </ButtonPrimary>
                </div>
            </Card>

            <ul
                v-else-if="records.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="evidence-base-list"
            >
                <li
                    v-for="record in records"
                    :key="record.id"
                    class="px-4 py-3"
                    data-testid="evidence-row"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-text" data-testid="evidence-type">
                                {{ typeLabel(record) }}
                            </p>
                            <p
                                v-if="record.author?.name"
                                class="text-meta text-text-muted"
                                data-testid="evidence-author"
                            >
                                {{ record.author.name }}
                            </p>
                            <p
                                v-if="termLabel(record)"
                                class="mt-1 text-meta text-text-muted"
                                data-testid="evidence-term"
                            >
                                {{ termLabel(record) }}
                            </p>
                            <p
                                v-if="record.body"
                                class="mt-2 text-body text-text"
                                data-testid="evidence-body"
                            >
                                {{ record.body }}
                            </p>
                        </div>
                        <p class="shrink-0 text-meta text-text-muted" data-testid="evidence-occurred-at">
                            {{ formatOccurredAt(record.occurred_at) }}
                        </p>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const filterChips = [
    { value: '', label: 'All' },
    { value: 'observation', label: 'Observation' },
    { value: 'intervention', label: 'Intervention' },
    { value: 'response', label: 'Pupil Response' },
    { value: 'review_note', label: 'Review note' },
    { value: 'import', label: 'Import' },
];

const DEFAULT_DOCUMENT_TITLE = 'GuidelyEdu';

const session = useSession();
const route = useRoute();
const router = useRouter();

const pupil = ref(null);
const records = ref([]);
const loading = ref(true);
const loadError = ref('');
const activeFilter = ref('');
const hasAnySubmitted = ref(false);
let loadSeq = 0;

const isTeacher = computed(() => session.role.value === 'teacher');

const pupilName = computed(() => {
    if (!pupil.value) {
        return 'Evidence Base';
    }

    return `${pupil.value.given_name ?? ''} ${pupil.value.family_name ?? ''}`.trim() || 'Evidence Base';
});

const pupilSubtitle = computed(() => {
    if (!pupil.value) {
        return '';
    }

    const parts = [];

    if (pupil.value.year_group) {
        parts.push(pupil.value.year_group);
    }

    if (pupil.value.send_status === 'sen_support') {
        parts.push('SEN Support');
    } else if (pupil.value.send_status === 'ehcp') {
        parts.push('EHCP');
    }

    return parts.join(' · ');
});

const emptyCopy = computed(() => {
    if (activeFilter.value && hasAnySubmitted.value) {
        return 'No Evidence Records match this filter.';
    }

    return 'No Evidence Records yet.';
});

const showCaptureCta = computed(() => {
    return isTeacher.value
        && !loadError.value
        && records.value.length === 0
        && !activeFilter.value
        && !hasAnySubmitted.value;
});

watch(
    pupilName,
    (title) => {
        document.title = title;

        if (route.name === 'pupil-detail') {
            route.meta.title = title;
        }
    },
    { immediate: true },
);

watch(
    () => route.params.id,
    async (id) => {
        if (!id) {
            pupil.value = null;
            records.value = [];
            hasAnySubmitted.value = false;
            loadError.value = 'Unable to load Evidence Base.';
            loading.value = false;

            return;
        }

        await loadPage();
    },
);

onMounted(async () => {
    await loadPage();
});

onUnmounted(() => {
    document.title = DEFAULT_DOCUMENT_TITLE;
});

/**
 * @param {string} value
 */
async function setFilter(value) {
    if (activeFilter.value === value) {
        return;
    }

    activeFilter.value = value;
    await loadEvidence({ seq: loadSeq });
}

function goToCapture() {
    router.push({ name: 'capture' });
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

/**
 * @param {{ type?: string, source?: string|null }} record
 */
function typeLabel(record) {
    if (record.source === 'import') {
        const base = typeLabelFromType(record.type);

        return `${base} (Import)`;
    }

    return typeLabelFromType(record.type);
}

/**
 * @param {string|undefined} type
 */
function typeLabelFromType(type) {
    if (type === 'intervention') {
        return 'Intervention';
    }

    if (type === 'response') {
        return 'Pupil Response';
    }

    if (type === 'review_note') {
        return 'Review note';
    }

    if (type === 'observation') {
        return 'Observation';
    }

    return type ? String(type) : 'Unknown';
}

/**
 * @param {{ setting?: { label?: string }|null, provision?: { label?: string }|null, related_intervention?: { provision?: { label?: string }|null }|null }} record
 */
function termLabel(record) {
    if (record.setting?.label) {
        return record.setting.label;
    }

    if (record.provision?.label) {
        return record.provision.label;
    }

    if (record.related_intervention?.provision?.label) {
        return `Related: ${record.related_intervention.provision.label}`;
    }

    return '';
}

/**
 * @param {string|undefined} iso
 */
function formatOccurredAt(iso) {
    if (!iso) {
        return '—';
    }

    const parsed = new Date(iso);

    if (Number.isNaN(parsed.getTime())) {
        return iso;
    }

    return parsed.toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/London',
    });
}

async function loadPage() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    activeFilter.value = '';
    hasAnySubmitted.value = false;
    pupil.value = null;
    records.value = [];

    try {
        const [pupilOk, evidenceOk] = await Promise.all([
            loadPupil(seq),
            loadEvidence({ trackUnfiltered: true, seq }),
        ]);

        if (seq !== loadSeq) {
            return;
        }

        if (!pupilOk || !evidenceOk) {
            records.value = [];
            loadError.value = 'Unable to load Evidence Base.';
        }
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        records.value = [];
        loadError.value = 'Unable to load Evidence Base.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {number} seq
 * @returns {Promise<boolean>}
 */
async function loadPupil(seq) {
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}`);

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            pupil.value = null;

            return false;
        }

        const payload = await response.json();
        pupil.value = isRecord(payload.data) ? payload.data : null;

        return pupil.value != null;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        pupil.value = null;

        return false;
    }
}

/**
 * @param {{ trackUnfiltered?: boolean, seq?: number }} [options]
 * @returns {Promise<boolean>}
 */
async function loadEvidence(options = {}) {
    const { trackUnfiltered = false, seq = loadSeq } = options;
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    const query = activeFilter.value ? `?filter=${encodeURIComponent(activeFilter.value)}` : '';

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/evidence${query}`);

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            records.value = [];

            if (!trackUnfiltered) {
                loadError.value = 'Unable to load Evidence Base.';
            }

            return false;
        }

        const payload = await response.json();
        records.value = asArray(payload.data).filter(isRecord);

        if (trackUnfiltered || !activeFilter.value) {
            hasAnySubmitted.value = records.value.length > 0;
        }

        if (!trackUnfiltered) {
            loadError.value = '';
        }

        return true;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        records.value = [];

        if (!trackUnfiltered) {
            loadError.value = 'Unable to load Evidence Base.';
        }

        return false;
    }
}
</script>
