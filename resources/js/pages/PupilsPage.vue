<template>
    <div data-testid="pupils-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">{{ pageTitle }}</h1>
                <p class="mt-1 text-body text-text-muted">
                    View Pupils in your list, including documentation status.
                </p>
            </div>
            <ButtonSecondary
                v-if="isSenco && !loading && pupils.length > 0 && !showAddForm"
                data-testid="pupils-add-open"
                @click="openAddForm"
            >
                Add Pupil
            </ButtonSecondary>
        </div>

        <div class="mt-6 max-w-md">
            <label class="block text-body text-text" for="pupils-search">Search</label>
            <input
                id="pupils-search"
                v-model="searchQuery"
                type="search"
                placeholder="Search by name or year group"
                class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="pupils-search"
                title="Search is limited to Pupils within your scope"
            >
        </div>

        <p
            v-if="schoolsLoadError"
            class="mt-4 text-body text-danger"
            data-testid="pupils-schools-error"
            role="alert"
        >
            {{ schoolsLoadError }}
        </p>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="pupils-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="pupils-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <template v-else>
            <Card
                v-if="pupils.length === 0 && !showAddForm"
                class="mt-6"
                data-testid="pupils-empty"
            >
                <p class="text-body text-text">No Pupils in your list.</p>
                <div
                    v-if="isSenco"
                    class="mt-4 flex flex-wrap gap-3"
                    data-testid="pupils-empty-ctas"
                >
                    <ButtonSecondary
                        data-testid="pupils-import-cta"
                        @click="goToImport"
                    >
                        Import
                    </ButtonSecondary>
                    <ButtonPrimary
                        data-testid="pupils-add-cta"
                        @click="openAddForm"
                    >
                        Add Pupil
                    </ButtonPrimary>
                </div>
            </Card>

            <Card
                v-else-if="pupils.length > 0 && filteredPupils.length === 0"
                class="mt-6"
                data-testid="pupils-search-empty"
            >
                <p class="text-body text-text-muted">No Pupils match your search.</p>
            </Card>

            <ul
                v-else-if="filteredPupils.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="pupils-list"
            >
                <li
                    v-for="pupil in filteredPupils"
                    :key="pupil.id"
                    data-testid="pupil-row"
                >
                    <RouterLink
                        :to="{ name: 'pupil-detail', params: { id: pupil.id } }"
                        class="flex flex-col gap-2 px-4 py-3 text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring sm:flex-row sm:items-center sm:justify-between"
                        :data-testid="`pupil-row-link-${pupil.id}`"
                    >
                        <div class="min-w-0">
                            <p class="text-body font-medium text-text" data-testid="pupil-name">
                                {{ displayName(pupil) }}
                            </p>
                            <p class="text-meta text-text-muted" data-testid="pupil-year">
                                {{ pupil.year_group ?? '—' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                            <StatusPill :status="pupil.documentation_status ?? 'not-started'" />
                            <span
                                class="text-meta text-text-muted"
                                data-testid="pupil-next-review"
                            >
                                Next review: {{ formatNextReview(pupil) }}
                            </span>
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </template>

        <Card
            v-if="isSenco && showAddForm"
            class="mt-6"
            data-testid="pupils-add-form"
        >
            <h2 class="text-body font-semibold text-text">Add Pupil</h2>
            <p class="mt-1 text-body text-text-muted">
                Create a Pupil working record for an accessible School. Need categories can be set later.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="createPupil">
                <div>
                    <label class="block text-body text-text" for="pupil-school">School</label>
                    <select
                        id="pupil-school"
                        v-model="addForm.school_id"
                        required
                        class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-school"
                    >
                        <option disabled value="">Select a School</option>
                        <option
                            v-for="school in schools"
                            :key="school.id"
                            :value="school.id"
                        >
                            {{ school.name }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.school_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-school_id"
                    >
                        {{ fieldErrors.school_id }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-given-name">Given name</label>
                    <input
                        id="pupil-given-name"
                        v-model="addForm.given_name"
                        type="text"
                        required
                        class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-given-name"
                    >
                    <p
                        v-if="fieldErrors.given_name"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-given_name"
                    >
                        {{ fieldErrors.given_name }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-family-name">Family name</label>
                    <input
                        id="pupil-family-name"
                        v-model="addForm.family_name"
                        type="text"
                        required
                        class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-family-name"
                    >
                    <p
                        v-if="fieldErrors.family_name"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-family_name"
                    >
                        {{ fieldErrors.family_name }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-year-group">Year group</label>
                    <input
                        id="pupil-year-group"
                        v-model="addForm.year_group"
                        type="text"
                        required
                        class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-year-group"
                    >
                    <p
                        v-if="fieldErrors.year_group"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-year_group"
                    >
                        {{ fieldErrors.year_group }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-send-status">SEND status</label>
                    <select
                        id="pupil-send-status"
                        v-model="addForm.send_status"
                        required
                        class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-send-status"
                    >
                        <option value="sen_support">SEN Support</option>
                        <option value="ehcp">EHCP</option>
                        <option value="neither">Neither</option>
                    </select>
                    <p
                        v-if="fieldErrors.send_status"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-send_status"
                    >
                        {{ fieldErrors.send_status }}
                    </p>
                </div>

                <p
                    v-if="createError"
                    class="text-body text-danger"
                    data-testid="pupils-create-error"
                    role="alert"
                >
                    {{ createError }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="creating"
                        data-testid="pupils-create-submit"
                    >
                        {{ creating ? 'Saving…' : 'Save Pupil' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="creating"
                        data-testid="pupils-add-cancel"
                        @click="closeAddForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Card>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import StatusPill from '../shared/ui/StatusPill.vue';

const session = useSession();
const route = useRoute();
const router = useRouter();

const isSenco = computed(() => session.role.value === 'senco');
const isTeacherOrSupport = computed(() =>
    ['teacher', 'support_staff'].includes(session.role.value),
);

const pageTitle = computed(() => {
    if (isTeacherOrSupport.value) {
        return 'My Pupils';
    }

    return 'Pupils';
});

watch(
    pageTitle,
    (title) => {
        document.title = title;

        if (route.name === 'pupils') {
            route.meta.title = title;
        }
    },
    { immediate: true },
);

const pupils = ref([]);
const schools = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const showAddForm = ref(false);
const creating = ref(false);
const createError = ref('');
const schoolsLoadError = ref('');
const fieldErrors = reactive({
    school_id: '',
    given_name: '',
    family_name: '',
    year_group: '',
    send_status: '',
});

const addForm = reactive({
    school_id: '',
    given_name: '',
    family_name: '',
    year_group: '',
    send_status: 'neither',
});

const filteredPupils = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return pupils.value;
    }

    return pupils.value.filter((pupil) => {
        const name = displayName(pupil).toLowerCase();
        const year = String(pupil.year_group ?? '').toLowerCase();

        return name.includes(query) || year.includes(query);
    });
});

onMounted(async () => {
    await loadPupils();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {{ given_name?: string, family_name?: string }} pupil
 */
function displayName(pupil) {
    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim();
}

/**
 * Next Review Cycle is Epic 5 — show em dash until present on the resource.
 *
 * @param {{ next_review_at?: string|null, next_review_cycle_date?: string|null }} pupil
 */
function formatNextReview(pupil) {
    const value = pupil.next_review_at ?? pupil.next_review_cycle_date ?? null;

    if (value == null || value === '') {
        return '—';
    }

    return value;
}

function clearFieldErrors() {
    fieldErrors.school_id = '';
    fieldErrors.given_name = '';
    fieldErrors.family_name = '';
    fieldErrors.year_group = '';
    fieldErrors.send_status = '';
}

function resetAddForm() {
    addForm.school_id = schools.value[0]?.id ?? '';
    addForm.given_name = '';
    addForm.family_name = '';
    addForm.year_group = '';
    addForm.send_status = 'neither';
    createError.value = '';
    clearFieldErrors();
}

function goToImport() {
    router.push('/import');
}

async function openAddForm() {
    schoolsLoadError.value = '';
    createError.value = '';
    clearFieldErrors();

    if (schools.value.length === 0) {
        await loadSchools();
    }

    if (schools.value.length === 0) {
        schoolsLoadError.value =
            createError.value || 'No accessible Schools available to add a Pupil.';
        createError.value = '';
        showAddForm.value = false;

        return;
    }

    showAddForm.value = true;

    if (!addForm.school_id) {
        addForm.school_id = schools.value[0]?.id ?? '';
    }
}

function closeAddForm() {
    showAddForm.value = false;
    resetAddForm();
}

async function loadPupils() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/pupils');

        if (!response.ok) {
            loadError.value = 'Unable to load Pupils.';
            pupils.value = [];
            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        pupils.value = rows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Pupils.';
        pupils.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadSchools() {
    try {
        const response = await apiFetch('/api/v1/schools?active=1');

        if (!response.ok) {
            createError.value = 'Unable to load Schools for Add Pupil.';
            schools.value = [];
            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        schools.value = rows.filter(isRecord);
    } catch {
        createError.value = 'Unable to load Schools for Add Pupil.';
        schools.value = [];
    }
}

async function createPupil() {
    creating.value = true;
    createError.value = '';
    clearFieldErrors();

    const body = {
        school_id: String(addForm.school_id).trim(),
        given_name: addForm.given_name.trim(),
        family_name: addForm.family_name.trim(),
        year_group: addForm.year_group.trim(),
        send_status: addForm.send_status,
    };

    try {
        const response = await apiFetch('/api/v1/pupils', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && payload.errors) {
                for (const [field, messages] of Object.entries(payload.errors)) {
                    if (field in fieldErrors) {
                        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
                    }
                }
            }

            createError.value = payload.message ?? 'Unable to create Pupil.';
            return;
        }

        const created = payload.data;

        if (isRecord(created) && created.id) {
            pupils.value = [...pupils.value, created].sort((a, b) =>
                displayName(a).localeCompare(displayName(b), 'en-GB'),
            );
        } else {
            await loadPupils();
        }

        searchQuery.value = '';
        showAddForm.value = false;
        resetAddForm();
    } catch {
        createError.value = 'Unable to create Pupil.';
    } finally {
        creating.value = false;
    }
}
</script>
