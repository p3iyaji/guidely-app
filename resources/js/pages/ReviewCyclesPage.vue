<template>
    <div data-testid="review-cycles-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">Review Cycles</h1>
                <p class="mt-1 text-body text-text-muted">
                    Open Review Cycles due in the selected window, including past-due.
                </p>
            </div>
            <ButtonSecondary
                v-if="isSenco && !loading && !showCreateForm"
                data-testid="review-cycles-create-open"
                @click="showCreateForm = true"
            >
                Create Review Cycle
            </ButtonSecondary>
        </div>

        <div
            class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
            data-testid="review-cycles-disclaimer"
            role="note"
        >
            Documentation evaluations support professional judgement. They are not diagnoses,
            funding decisions, or statutory determinations.
        </div>

        <div
            class="mt-4 flex flex-wrap gap-2"
            role="group"
            aria-label="Due window"
            data-testid="review-cycles-windows"
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
                :data-testid="`review-cycles-window-${days}`"
                @click="setWindow(days)"
            >
                {{ days }} days
            </button>
        </div>

        <div class="mt-4 max-w-md">
            <label class="block text-body text-text" for="review-cycles-search">Search</label>
            <input
                id="review-cycles-search"
                v-model="searchQuery"
                type="search"
                placeholder="Search by Pupil name"
                class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="review-cycles-search"
                title="Search is limited to Review Cycles within your scope"
                @keydown.enter.prevent="applySearch"
            >
        </div>

        <Card
            v-if="isSenco && showCreateForm"
            class="mt-6"
            data-testid="review-cycles-create-form"
        >
            <h2 class="text-body font-semibold text-text">Create Review Cycle</h2>
            <p class="mt-1 text-body text-text-muted">
                Plan an Annual Review or other cycle for a Pupil in an accessible School.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="createCycle">
                <div>
                    <label class="block text-body text-text" for="review-cycle-pupil">Pupil</label>
                    <select
                        id="review-cycle-pupil"
                        v-model="createForm.pupil_id"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="review-cycle-pupil"
                    >
                        <option value="">Select a Pupil</option>
                        <option
                            v-for="pupil in pupils"
                            :key="pupil.id"
                            :value="pupil.id"
                        >
                            {{ pupilName(pupil) }}
                        </option>
                    </select>
                    <p v-if="fieldErrors.pupil_id" class="mt-1 text-meta text-danger" data-testid="review-cycle-pupil-error">
                        {{ fieldErrors.pupil_id }}
                    </p>
                </div>

                <div>
                    <label class="block text-body text-text" for="review-cycle-type">Type</label>
                    <select
                        id="review-cycle-type"
                        v-model="createForm.type"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="review-cycle-type"
                    >
                        <option value="annual_review">Annual Review</option>
                        <option value="interim">Interim</option>
                        <option value="other">Other</option>
                    </select>
                    <p v-if="fieldErrors.type" class="mt-1 text-meta text-danger" data-testid="review-cycle-type-error">
                        {{ fieldErrors.type }}
                    </p>
                </div>

                <div>
                    <label class="block text-body text-text" for="review-cycle-due-on">Due date</label>
                    <input
                        id="review-cycle-due-on"
                        v-model="createForm.due_on"
                        type="date"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="review-cycle-due-on"
                    >
                    <p v-if="fieldErrors.due_on" class="mt-1 text-meta text-danger" data-testid="review-cycle-due-on-error">
                        {{ fieldErrors.due_on }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="createForm.ehcp_linked"
                        type="checkbox"
                        data-testid="review-cycle-ehcp-linked"
                    >
                    Linked to EHCP
                </label>

                <p v-if="pupilsLoadError" class="text-body text-danger" role="alert" data-testid="review-cycles-pupils-error">
                    {{ pupilsLoadError }}
                </p>
                <p v-if="createError" class="text-body text-danger" role="alert" data-testid="review-cycles-create-error">
                    {{ createError }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary type="submit" :disabled="creating" data-testid="review-cycle-create-submit">
                        {{ creating ? 'Creating…' : 'Create' }}
                    </ButtonPrimary>
                    <ButtonSecondary data-testid="review-cycle-create-cancel" @click="showCreateForm = false">
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Card>

        <p
            v-if="closeError"
            class="mt-4 text-body text-danger"
            role="alert"
            data-testid="review-cycles-close-error"
        >
            {{ closeError }}
        </p>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="review-cycles-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="review-cycles-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <Card
            v-else-if="cycles.length === 0"
            class="mt-6"
            data-testid="review-cycles-empty"
        >
            <p class="text-body text-text" data-testid="review-cycles-empty-copy">
                No Review Cycles due in this window.
            </p>
        </Card>

        <ul
            v-else
            class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
            data-testid="review-cycles-list"
        >
            <li
                v-for="cycle in cycles"
                :key="cycle.id"
                data-testid="review-cycle-row"
            >
                <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <RouterLink
                        :to="cycleLink(cycle)"
                        class="min-w-0 text-text hover:underline focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        :data-testid="`review-cycle-row-link-${cycle.id}`"
                    >
                        <p class="text-body font-medium text-text" data-testid="review-cycle-pupil-name">
                            {{ pupilName(cycle.pupil ?? cycle) }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="review-cycle-type-label">
                            {{ cycle.type_label || typeLabel(cycle.type) }}
                        </p>
                    </RouterLink>
                    <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                        <span
                            class="text-meta"
                            :class="isPastDue(cycle.due_on) ? 'text-danger' : 'text-text-muted'"
                            data-testid="review-cycle-due-on"
                        >
                            {{ formatDueOn(cycle.due_on) }}
                            <span v-if="isPastDue(cycle.due_on)"> (past due)</span>
                        </span>
                        <ButtonSecondary
                            v-if="isSenco"
                            data-testid="review-cycle-close"
                            :aria-label="`Close Review Cycle for ${pupilName(cycle.pupil ?? cycle)}`"
                            :disabled="closingId === cycle.id"
                            @click="closeCycle(cycle)"
                        >
                            {{ closingId === cycle.id ? 'Closing…' : 'Close' }}
                        </ButtonSecondary>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const windowOptions = [7, 30, 90];
const session = useSession();
const route = useRoute();
const router = useRouter();

const isSenco = computed(() => session.role.value === 'senco');

const cycles = ref([]);
const pupils = ref([]);
const loading = ref(true);
const loadError = ref('');
const creating = ref(false);
const createError = ref('');
const pupilsLoadError = ref('');
const showCreateForm = ref(false);
const closingId = ref('');
const closeError = ref('');
const searchQuery = ref(queryString(route.query.q));
const windowDays = ref(parseWindow(route.query.window));
let loadSeq = 0;
const fieldErrors = reactive({
    pupil_id: '',
    type: '',
    due_on: '',
});
const createForm = reactive({
    pupil_id: '',
    type: 'annual_review',
    due_on: '',
    ehcp_linked: false,
});

onMounted(async () => {
    await Promise.all([loadCycles(), loadPupilsIfSenco()]);
});

watch(
    () => [route.query.q, route.query.window],
    async () => {
        searchQuery.value = queryString(route.query.q);
        windowDays.value = parseWindow(route.query.window);
        await loadCycles();
    },
);

/**
 * @param {number} days
 */
function setWindow(days) {
    windowDays.value = days;
    router.replace({
        path: '/review-cycles',
        query: {
            ...(searchQuery.value ? { q: searchQuery.value } : {}),
            window: String(days),
        },
    });
}

function applySearch() {
    router.replace({
        path: '/review-cycles',
        query: {
            ...(searchQuery.value ? { q: searchQuery.value } : {}),
            ...(windowDays.value !== 30 ? { window: String(windowDays.value) } : {}),
        },
    });
}

async function loadCycles() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    cycles.value = [];

    const params = new URLSearchParams();
    params.set('window', String(windowDays.value));

    if (searchQuery.value) {
        params.set('q', searchQuery.value);
    }

    try {
        const response = await apiFetch(`/api/v1/review-cycles?${params.toString()}`);

        if (seq !== loadSeq) {
            return;
        }

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Review Cycles.';

            return;
        }

        const payload = await response.json();
        cycles.value = asArray(payload.data).filter(isRecord);
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load Review Cycles.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

async function loadPupilsIfSenco() {
    if (!isSenco.value) {
        return;
    }

    pupilsLoadError.value = '';

    try {
        const response = await apiFetch('/api/v1/pupils');

        if (!response.ok) {
            pupilsLoadError.value = 'Unable to load Pupils.';

            return;
        }

        const payload = await response.json();
        pupils.value = asArray(payload.data).filter(isRecord);
    } catch {
        pupils.value = [];
        pupilsLoadError.value = 'Unable to load Pupils.';
    }
}

async function createCycle() {
    creating.value = true;
    createError.value = '';
    fieldErrors.pupil_id = '';
    fieldErrors.type = '';
    fieldErrors.due_on = '';

    try {
        const response = await apiFetch('/api/v1/review-cycles', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pupil_id: createForm.pupil_id,
                type: createForm.type,
                due_on: createForm.due_on,
                ehcp_linked: createForm.ehcp_linked,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && isRecord(payload.errors)) {
                for (const [field, messages] of Object.entries(payload.errors)) {
                    if (field in fieldErrors) {
                        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
                    }
                }
            }

            createError.value = payload.message ?? 'Unable to create Review Cycle.';

            return;
        }

        showCreateForm.value = false;
        createForm.pupil_id = '';
        createForm.type = 'annual_review';
        createForm.due_on = '';
        createForm.ehcp_linked = false;
        await loadCycles();
    } catch {
        createError.value = 'Unable to create Review Cycle.';
    } finally {
        creating.value = false;
    }
}

/**
 * @param {Record<string, unknown>} cycle
 */
async function closeCycle(cycle) {
    const id = String(cycle.id ?? '');

    if (id === '') {
        return;
    }

    closingId.value = id;
    closeError.value = '';

    try {
        const response = await apiFetch(`/api/v1/review-cycles/${id}/close`, {
            method: 'POST',
        });

        if (!response.ok) {
            closeError.value = 'Unable to close Review Cycle.';

            return;
        }

        await loadCycles();
    } catch {
        closeError.value = 'Unable to close Review Cycle.';
    } finally {
        closingId.value = '';
    }
}

/**
 * @param {Record<string, unknown>} cycle
 */
function cycleLink(cycle) {
    const pupilId = cycle.pupil_id ?? (isRecord(cycle.pupil) ? cycle.pupil.id : '');

    if (!pupilId) {
        return { name: 'review-cycles' };
    }

    return {
        name: 'pupil-detail',
        params: { id: String(pupilId) },
    };
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
 * @param {string|undefined} type
 */
function typeLabel(type) {
    const labels = {
        annual_review: 'Annual Review',
        interim: 'Interim',
        other: 'Other',
    };

    return labels[type] ?? (type || '—');
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
 * @param {string|undefined} dueOn
 */
function isPastDue(dueOn) {
    if (!dueOn) {
        return false;
    }

    const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Europe/London' });

    return dueOn < today;
}

/**
 * @param {unknown} value
 */
function parseWindow(value) {
    const parsed = Number(value);

    return windowOptions.includes(parsed) ? parsed : 30;
}

/**
 * @param {unknown} value
 */
function queryString(value) {
    return typeof value === 'string' ? value : '';
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
