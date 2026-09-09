<template>
    <div data-testid="pupils-page">
        <PageHero
            eyebrow="Pupils"
            :title="pageTitle"
            description="View Pupils in your list, including documentation status."
        >
            <template v-if="canManage && !loading && !loadError" #action>
                <ButtonPrimary
                    data-testid="pupils-add-open"
                    @click="openCreateForm"
                >
                    Add Pupil
                </ButtonPrimary>
            </template>
        </PageHero>

        <CrudSearch
            id="pupils-search"
            v-model="searchQuery"
            placeholder="Search by name or year group"
            title="Search is limited to Pupils within your scope"
            test-id="pupils-search"
        />

        <p
            v-if="schoolsLoadError"
            class="mb-4 rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="pupils-schools-error"
            role="alert"
        >
            {{ schoolsLoadError }}
        </p>

        <div v-if="loading" class="space-y-3" data-testid="pupils-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="pupils-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="pupils.length === 0"
                test-id="pupils-empty"
                actions-test-id="pupils-empty-ctas"
            >
                {{ isTeacherOrSupport
                    ? 'No Pupils assigned yet. Ask your SENCO to assign Pupils to you.'
                    : 'No Pupils in your list.' }}
                <template v-if="canManage" #actions>
                    <ButtonSecondary
                        data-testid="pupils-import-cta"
                        @click="goToImport"
                    >
                        Import
                    </ButtonSecondary>
                    <ButtonPrimary
                        data-testid="pupils-add-cta"
                        @click="openCreateForm"
                    >
                        Add Pupil
                    </ButtonPrimary>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="pupils.length > 0 && filteredPupils.length === 0"
                test-id="pupils-search-empty"
            >
                No Pupils match your search.
            </EmptyState>

            <DataTable
                v-else-if="filteredPupils.length > 0"
                test-id="pupils-list"
            >
                <template #head>
                    <tr>
                        <th class="px-4 py-3" scope="col">Pupil</th>
                        <th class="px-4 py-3" scope="col">Year</th>
                        <th class="px-4 py-3" scope="col">Documentation</th>
                        <th class="hidden px-4 py-3 lg:table-cell" scope="col">Assigned staff</th>
                        <th class="px-4 py-3" scope="col">Status</th>
                        <th class="px-4 py-3" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="pupil in filteredPupils"
                    :key="pupil.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="pupil-row"
                >
                    <td class="px-4 py-3">
                        <RouterLink
                            :to="{ name: 'pupil-detail', params: { id: pupil.id } }"
                            class="text-text hover:text-primary focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            :data-testid="`pupil-row-link-${pupil.id}`"
                        >
                            <p class="font-medium text-text" data-testid="pupil-name">
                                {{ displayName(pupil) }}
                            </p>
                            <p
                                v-if="needSummary(pupil)"
                                class="text-meta text-text-muted"
                                data-testid="pupil-need"
                            >
                                {{ needSummary(pupil) }}
                            </p>
                        </RouterLink>
                    </td>
                    <td class="px-4 py-3 text-text-muted" data-testid="pupil-year">
                        {{ pupil.year_group ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-meta text-text-muted" data-testid="pupil-next-review">
                            Next review: {{ formatNextReview(pupil) }}
                        </p>
                    </td>
                    <td class="hidden px-4 py-3 text-meta text-text-muted lg:table-cell" data-testid="pupil-assignees">
                        {{ assigneeSummary(pupil) || '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <StatusPill :status="pupil.documentation_status ?? 'not-started'" />
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <RouterLink
                                :to="{ name: 'pupil-detail', params: { id: pupil.id } }"
                                class="inline-flex size-9 items-center justify-center rounded-md text-primary hover:bg-primary-soft focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                :aria-label="`Open ${displayName(pupil)}`"
                                :title="`Open ${displayName(pupil)}`"
                            >
                                <AppIcon name="open" class="size-4.5" />
                                <span class="sr-only">Open {{ displayName(pupil) }}</span>
                            </RouterLink>
                            <template v-if="canManage">
                                <TableAction
                                    icon="edit"
                                    :label="`Edit ${displayName(pupil)}`"
                                    :data-testid="`pupil-edit-${pupil.id}`"
                                    @click="openEditForm(pupil)"
                                />
                                <TableAction
                                    icon="delete"
                                    :label="`Delete ${displayName(pupil)}`"
                                    tone="danger"
                                    :data-testid="`pupil-delete-${pupil.id}`"
                                    @click="openDeleteConfirm(pupil)"
                                />
                            </template>
                        </div>
                    </td>
                </tr>
            </DataTable>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit Pupil' : 'Add Pupil'"
            :close-disabled="saving"
            data-testid="pupils-add-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                {{ formMode === 'edit'
                    ? 'Update this Pupil working record, Need categories, and Teacher assignments.'
                    : 'Create a Pupil working record for an accessible School. Need categories and Teacher assignments can be set now.' }}
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submitPupilForm">
                <div>
                    <label class="block text-body text-text" for="pupil-school">School</label>
                    <select
                        id="pupil-school"
                        v-model="pupilForm.school_id"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
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
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-body text-text" for="pupil-given-name">Given name</label>
                        <input
                            id="pupil-given-name"
                            v-model="pupilForm.given_name"
                            type="text"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
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
                            v-model="pupilForm.family_name"
                            type="text"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
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
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-body text-text" for="pupil-year-group">Year group</label>
                        <input
                            id="pupil-year-group"
                            v-model="pupilForm.year_group"
                            type="text"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
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
                            v-model="pupilForm.send_status"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
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
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-body text-text" for="pupil-mis-key">MIS key (optional)</label>
                        <input
                            id="pupil-mis-key"
                            v-model="pupilForm.mis_key"
                            type="text"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="pupil-mis-key"
                        >
                        <p
                            v-if="fieldErrors.mis_key"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-mis_key"
                        >
                            {{ fieldErrors.mis_key }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="pupil-date-of-birth">Date of birth (optional)</label>
                        <input
                            id="pupil-date-of-birth"
                            v-model="pupilForm.date_of_birth"
                            type="date"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="pupil-date-of-birth"
                        >
                        <p
                            v-if="fieldErrors.date_of_birth"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-date_of_birth"
                        >
                            {{ fieldErrors.date_of_birth }}
                        </p>
                    </div>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-notes">Notes (optional)</label>
                    <textarea
                        id="pupil-notes"
                        v-model="pupilForm.notes"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-notes"
                    />
                    <p
                        v-if="fieldErrors.notes"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-notes"
                    >
                        {{ fieldErrors.notes }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-primary-need">Primary Need</label>
                    <select
                        id="pupil-primary-need"
                        v-model="pupilForm.primary_need_term_id"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-primary-need"
                    >
                        <option value="">None</option>
                        <option
                            v-for="term in needTerms"
                            :key="term.id"
                            :value="term.id"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.primary_need_term_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-primary_need_term_id"
                    >
                        {{ fieldErrors.primary_need_term_id }}
                    </p>
                </div>
                <div v-if="pupilForm.primary_need_term_id">
                    <label class="block text-body text-text" for="pupil-primary-need-notes">Primary Need notes (optional)</label>
                    <textarea
                        id="pupil-primary-need-notes"
                        v-model="pupilForm.primary_need_notes"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-primary-need-notes"
                    />
                </div>
                <div>
                    <label class="block text-body text-text" for="pupil-secondary-need">Secondary Need</label>
                    <select
                        id="pupil-secondary-need"
                        v-model="pupilForm.secondary_need_term_id"
                        :disabled="!pupilForm.primary_need_term_id"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                        data-testid="pupil-secondary-need"
                    >
                        <option value="">None</option>
                        <option
                            v-for="term in secondaryNeedOptions"
                            :key="term.id"
                            :value="term.id"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.secondary_need_term_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-secondary_need_term_id"
                    >
                        {{ fieldErrors.secondary_need_term_id }}
                    </p>
                </div>
                <div v-if="pupilForm.secondary_need_term_id">
                    <label class="block text-body text-text" for="pupil-secondary-need-notes">Secondary Need notes (optional)</label>
                    <textarea
                        id="pupil-secondary-need-notes"
                        v-model="pupilForm.secondary_need_notes"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-secondary-need-notes"
                    />
                </div>
                <fieldset data-testid="pupil-assignments">
                    <legend class="text-body text-text">Assigned Teachers and Support Staff</legend>
                    <p class="mt-1 text-meta text-text-muted">
                        Teachers only see Pupils they are assigned to.
                    </p>
                    <p
                        v-if="staffLoadError"
                        class="mt-2 text-meta text-danger"
                        data-testid="pupil-assignments-error"
                    >
                        {{ staffLoadError }}
                    </p>
                    <p
                        v-else-if="assignableStaff.length === 0"
                        class="mt-2 text-meta text-text-muted"
                        data-testid="pupil-assignments-empty"
                    >
                        No Teachers or Support Staff have access to this School yet.
                    </p>
                    <div v-else class="mt-2 space-y-2">
                        <label
                            v-for="staff in assignableStaff"
                            :key="staff.id"
                            class="flex items-center gap-2 text-body text-text"
                        >
                            <input
                                v-model="selectedAssigneeIds"
                                type="checkbox"
                                :value="String(staff.id)"
                                :data-testid="`pupil-assignee-${staff.id}`"
                            >
                            {{ staff.name }}
                            <span class="text-meta text-text-muted">({{ staff.role === 'support_staff' ? 'Support Staff' : 'Teacher' }})</span>
                        </label>
                    </div>
                </fieldset>

                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="pupils-create-error"
                    role="alert"
                >
                    {{ formError }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="pupils-create-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save Pupil' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="pupils-add-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedPupil !== null"
            title="Delete Pupil"
            :close-disabled="saving"
            data-testid="pupils-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete {{ selectedPupil ? displayName(selectedPupil) : 'this Pupil' }}? They will leave the active list.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="pupils-create-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="pupils-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Confirm delete' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="pupils-delete-cancel"
                    @click="closeForm"
                >
                    Cancel
                </ButtonSecondary>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import AppIcon from '../shared/ui/AppIcon.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import CrudSearch from '../shared/ui/CrudSearch.vue';
import DataTable from '../shared/ui/DataTable.vue';
import EmptyState from '../shared/ui/EmptyState.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';
import PageHero from '../shared/ui/PageHero.vue';
import StatusPill from '../shared/ui/StatusPill.vue';
import TableAction from '../shared/ui/TableAction.vue';

const EVALUATING_POLL_MS = 2000;
const EVALUATING_POLL_MAX_ATTEMPTS = 30;

const session = useSession();
const route = useRoute();
const router = useRouter();

const canManage = computed(() =>
    ['senco', 'tenant_admin'].includes(session.role.value),
);
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
const needTerms = ref([]);
const assignableStaff = ref([]);
const selectedAssigneeIds = ref([]);
const staffLoadError = ref('');
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedPupil = ref(null);
const saving = ref(false);
const formError = ref('');
const schoolsLoadError = ref('');
const fieldErrors = reactive({
    school_id: '',
    given_name: '',
    family_name: '',
    year_group: '',
    send_status: '',
    mis_key: '',
    date_of_birth: '',
    notes: '',
    primary_need_term_id: '',
    primary_need_notes: '',
    secondary_need_term_id: '',
    secondary_need_notes: '',
});

const pupilForm = reactive({
    school_id: '',
    given_name: '',
    family_name: '',
    year_group: '',
    send_status: 'neither',
    mis_key: '',
    date_of_birth: '',
    notes: '',
    primary_need_term_id: '',
    primary_need_notes: '',
    secondary_need_term_id: '',
    secondary_need_notes: '',
});

const secondaryNeedOptions = computed(() =>
    needTerms.value.filter((term) => String(term.id) !== String(pupilForm.primary_need_term_id)),
);

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

const hasEvaluatingPupils = computed(() =>
    pupils.value.some((pupil) => pupil.documentation_status === 'evaluating'),
);

/** @type {ReturnType<typeof setInterval>|null} */
let evaluatingPollTimer = null;
let evaluatingPollAttempts = 0;

onMounted(async () => {
    await loadPupils();
});

onUnmounted(() => {
    stopEvaluatingPoll();
});

watch(
    () => pupilForm.school_id,
    (schoolId) => {
        if (formMode.value === 'create' || formMode.value === 'edit') {
            void loadAssignableStaff(schoolId);
        }
    },
);

watch(
    () => pupilForm.primary_need_term_id,
    (primaryId) => {
        if (!primaryId) {
            pupilForm.secondary_need_term_id = '';
            pupilForm.secondary_need_notes = '';
            pupilForm.primary_need_notes = '';
        }

        if (pupilForm.secondary_need_term_id === primaryId) {
            pupilForm.secondary_need_term_id = '';
            pupilForm.secondary_need_notes = '';
        }
    },
);

watch(hasEvaluatingPupils, (shouldPoll) => {
    if (shouldPoll) {
        startEvaluatingPoll();
    } else {
        stopEvaluatingPoll();
    }
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

function startEvaluatingPoll() {
    if (evaluatingPollTimer !== null) {
        return;
    }

    evaluatingPollAttempts = 0;
    evaluatingPollTimer = setInterval(() => {
        void refreshPupilsQuietly();
    }, EVALUATING_POLL_MS);
}

function stopEvaluatingPoll() {
    if (evaluatingPollTimer === null) {
        return;
    }

    clearInterval(evaluatingPollTimer);
    evaluatingPollTimer = null;
    evaluatingPollAttempts = 0;
}

/**
 * Soft refetch while evaluating — does not flip the full-page loading skeleton.
 */
async function refreshPupilsQuietly() {
    if (typeof document !== 'undefined' && document.hidden) {
        return;
    }

    if (evaluatingPollAttempts >= EVALUATING_POLL_MAX_ATTEMPTS) {
        stopEvaluatingPoll();

        return;
    }

    evaluatingPollAttempts += 1;

    try {
        const response = await apiFetch('/api/v1/pupils');

        if (response.status === 401 || response.status === 403) {
            stopEvaluatingPoll();

            return;
        }

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (!Array.isArray(payload?.data)) {
            return;
        }

        pupils.value = payload.data.filter(isRecord);
    } catch {
        // Keep showing the last known list; next poll retries.
    }
}

/**
 * @param {{ given_name?: string, family_name?: string }} pupil
 */
function displayName(pupil) {
    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim();
}

/**
 * @param {{ primary_need?: { label?: string }, secondary_need?: { label?: string } }} pupil
 */
function needSummary(pupil) {
    const primary = pupil.primary_need?.label;
    const secondary = pupil.secondary_need?.label;

    if (!primary) {
        return '';
    }

    return secondary ? `Need: ${primary} · ${secondary}` : `Need: ${primary}`;
}

/**
 * @param {{ assigned_staff?: unknown }} pupil
 */
function assigneeSummary(pupil) {
    const staff = Array.isArray(pupil.assigned_staff) ? pupil.assigned_staff : [];
    const names = staff.map((row) => (isRecord(row) ? String(row.name ?? '') : '')).filter(Boolean);

    return names.join(', ');
}

/**
 * Next open Review Cycle due date, displayed Europe/London en-GB.
 *
 * @param {{ next_review_at?: string|null, next_review_cycle_date?: string|null }} pupil
 */
function formatNextReview(pupil) {
    const value = pupil.next_review_at ?? pupil.next_review_cycle_date ?? null;

    if (value == null || value === '') {
        return '—';
    }

    const parsed = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleDateString('en-GB', {
        dateStyle: 'medium',
        timeZone: 'Europe/London',
    });
}

/**
 * @param {unknown} value
 */
function optionalText(value) {
    const trimmed = String(value ?? '').trim();

    return trimmed === '' ? null : trimmed;
}

function clearFieldErrors() {
    fieldErrors.school_id = '';
    fieldErrors.given_name = '';
    fieldErrors.family_name = '';
    fieldErrors.year_group = '';
    fieldErrors.send_status = '';
    fieldErrors.mis_key = '';
    fieldErrors.date_of_birth = '';
    fieldErrors.notes = '';
    fieldErrors.primary_need_term_id = '';
    fieldErrors.primary_need_notes = '';
    fieldErrors.secondary_need_term_id = '';
    fieldErrors.secondary_need_notes = '';
}

function resetPupilForm() {
    pupilForm.school_id = schools.value[0]?.id ?? '';
    pupilForm.given_name = '';
    pupilForm.family_name = '';
    pupilForm.year_group = '';
    pupilForm.send_status = 'neither';
    pupilForm.mis_key = '';
    pupilForm.date_of_birth = '';
    pupilForm.notes = '';
    pupilForm.primary_need_term_id = '';
    pupilForm.primary_need_notes = '';
    pupilForm.secondary_need_term_id = '';
    pupilForm.secondary_need_notes = '';
    selectedAssigneeIds.value = [];
    formError.value = '';
    clearFieldErrors();
}

function goToImport() {
    router.push('/import');
}

function closeForm() {
    formMode.value = null;
    selectedPupil.value = null;
    resetPupilForm();
}

async function ensureSchoolsLoaded() {
    schoolsLoadError.value = '';
    formError.value = '';
    clearFieldErrors();

    if (schools.value.length === 0) {
        await loadSchools();
    }

    if (schools.value.length === 0) {
        schoolsLoadError.value =
            formError.value || 'No accessible Schools available to add a Pupil.';
        formError.value = '';

        return false;
    }

    return true;
}

async function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    const ready = await ensureSchoolsLoaded();

    if (!ready) {
        return;
    }

    resetPupilForm();
    selectedPupil.value = null;
    formMode.value = 'create';
    await loadNeedTerms();
    await loadAssignableStaff(pupilForm.school_id);
}

/**
 * @param {Record<string, unknown>} pupil
 */
async function openEditForm(pupil) {
    if (!canManage.value) {
        return;
    }

    const ready = await ensureSchoolsLoaded();

    if (!ready) {
        return;
    }

    resetPupilForm();
    selectedPupil.value = pupil;
    pupilForm.school_id = String(pupil.school_id ?? schools.value[0]?.id ?? '');
    pupilForm.given_name = String(pupil.given_name ?? '');
    pupilForm.family_name = String(pupil.family_name ?? '');
    pupilForm.year_group = String(pupil.year_group ?? '');
    pupilForm.send_status = typeof pupil.send_status === 'string' ? pupil.send_status : 'neither';
    pupilForm.mis_key = pupil.mis_key == null ? '' : String(pupil.mis_key);
    pupilForm.date_of_birth = pupil.date_of_birth == null ? '' : String(pupil.date_of_birth);
    pupilForm.notes = pupil.notes == null ? '' : String(pupil.notes);
    pupilForm.primary_need_term_id = pupil.primary_need && isRecord(pupil.primary_need)
        ? String(pupil.primary_need.id ?? '')
        : '';
    pupilForm.primary_need_notes = pupil.primary_need && isRecord(pupil.primary_need)
        ? String(pupil.primary_need.notes ?? '')
        : '';
    pupilForm.secondary_need_term_id = pupil.secondary_need && isRecord(pupil.secondary_need)
        ? String(pupil.secondary_need.id ?? '')
        : '';
    pupilForm.secondary_need_notes = pupil.secondary_need && isRecord(pupil.secondary_need)
        ? String(pupil.secondary_need.notes ?? '')
        : '';
    selectedAssigneeIds.value = Array.isArray(pupil.assigned_staff)
        ? pupil.assigned_staff.map((row) => String(isRecord(row) ? row.id : '')).filter(Boolean)
        : [];
    formMode.value = 'edit';
    await loadNeedTerms();
    await loadAssignableStaff(pupilForm.school_id);
}

/**
 * @param {Record<string, unknown>} pupil
 */
function openDeleteConfirm(pupil) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedPupil.value = pupil;
    formMode.value = 'delete';
}

function sortPupils(rows) {
    return [...rows].sort((a, b) => {
        const family = String(a.family_name ?? '').localeCompare(String(b.family_name ?? ''), 'en-GB');

        if (family !== 0) {
            return family;
        }

        return displayName(a).localeCompare(displayName(b), 'en-GB');
    });
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

async function loadNeedTerms() {
    try {
        const response = await apiFetch('/api/v1/ontology/need-terms', {
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            needTerms.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        needTerms.value = rows.filter(isRecord);
    } catch {
        needTerms.value = [];
    }
}

/**
 * @param {string} schoolId
 */
async function loadAssignableStaff(schoolId) {
    staffLoadError.value = '';

    if (!schoolId) {
        assignableStaff.value = [];

        return;
    }

    try {
        const response = await apiFetch(`/api/v1/schools/${schoolId}/assignable-staff`, {
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            assignableStaff.value = [];
            staffLoadError.value = 'Unable to load staff for this School.';

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        assignableStaff.value = rows.filter(isRecord);
    } catch {
        assignableStaff.value = [];
        staffLoadError.value = 'Unable to load staff for this School.';
    }
}

/**
 * @param {Record<string, unknown>} pupil
 * @param {boolean} isCreate
 * @returns {Promise<Record<string, unknown>|false>}
 */
async function syncAssignments(pupil, isCreate) {
    const pupilId = String(pupil.id ?? '');
    const current = new Set(
        (Array.isArray(pupil.assigned_staff) ? pupil.assigned_staff : [])
            .map((row) => String(isRecord(row) ? row.id : ''))
            .filter(Boolean),
    );
    const selected = new Set(selectedAssigneeIds.value.map(String));
    let latest = pupil;

    for (const userId of selected) {
        if (current.has(userId)) {
            continue;
        }

        const response = await apiFetch(`/api/v1/pupils/${pupilId}/assignments`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: Number(userId) || userId }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            formError.value = payload.message ?? 'Pupil saved, but assigning staff failed.';

            return false;
        }

        if (isRecord(payload.data)) {
            latest = payload.data;
        }
    }

    if (!isCreate) {
        for (const userId of current) {
            if (selected.has(userId)) {
                continue;
            }

            const response = await apiFetch(`/api/v1/pupils/${pupilId}/assignments/${userId}`, {
                method: 'DELETE',
            });

            if (!response.ok && response.status !== 204) {
                formError.value = 'Pupil saved, but removing an assignment failed.';

                return false;
            }
        }
    }

    return {
        ...latest,
        assigned_staff: assignableStaff.value.filter((staff) => selected.has(String(staff.id))),
    };
}

async function loadSchools() {
    try {
        const response = await apiFetch('/api/v1/schools?active=1');

        if (!response.ok) {
            formError.value = 'Unable to load Schools for Add Pupil.';
            schools.value = [];
            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        schools.value = rows.filter(isRecord);
    } catch {
        formError.value = 'Unable to load Schools for Add Pupil.';
        schools.value = [];
    }
}

async function submitPupilForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        school_id: String(pupilForm.school_id).trim(),
        given_name: pupilForm.given_name.trim(),
        family_name: pupilForm.family_name.trim(),
        year_group: pupilForm.year_group.trim(),
        send_status: pupilForm.send_status,
        mis_key: optionalText(pupilForm.mis_key),
        date_of_birth: optionalText(pupilForm.date_of_birth),
        notes: optionalText(pupilForm.notes),
        primary_need_term_id: optionalText(pupilForm.primary_need_term_id),
        primary_need_notes: optionalText(pupilForm.primary_need_notes),
        secondary_need_term_id: optionalText(pupilForm.secondary_need_term_id),
        secondary_need_notes: optionalText(pupilForm.secondary_need_notes),
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate ? '/api/v1/pupils' : `/api/v1/pupils/${selectedPupil.value?.id}`;
    const method = isCreate ? 'POST' : 'PATCH';

    try {
        const response = await apiFetch(url, {
            method,
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

            formError.value = payload.message ?? (isCreate ? 'Unable to create Pupil.' : 'Unable to update Pupil.');
            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            const synced = await syncAssignments(saved, isCreate);

            if (synced === false) {
                return;
            }

            const merged = isRecord(synced) ? synced : saved;

            if (isCreate) {
                pupils.value = sortPupils([...pupils.value, merged]);
            } else {
                pupils.value = sortPupils(
                    pupils.value.map((row) => (row.id === merged.id ? merged : row)),
                );
            }
        } else {
            await loadPupils();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create Pupil.' : 'Unable to update Pupil.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const pupilId = selectedPupil.value?.id;

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete Pupil.';

            return;
        }

        pupils.value = pupils.value.filter((row) => row.id !== pupilId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete Pupil.';
    } finally {
        saving.value = false;
    }
}
</script>
