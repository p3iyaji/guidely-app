<template>
    <div data-testid="schools-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">Schools</h1>
                <p class="mt-1 text-body text-text-muted">
                    {{ pageIntro }}
                </p>
            </div>
            <ButtonSecondary
                v-if="canManage && !loading && !loadError"
                data-testid="schools-add-open"
                @click="openCreateForm"
            >
                Add School
            </ButtonSecondary>
        </div>

        <div class="mt-6 max-w-md">
            <label class="block text-body text-text" for="schools-search">Search</label>
            <input
                id="schools-search"
                v-model="searchQuery"
                type="search"
                placeholder="Search by name, city, or postcode"
                class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="schools-search"
            >
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="schools-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="schools-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <template v-else>
            <Card
                v-if="schools.length === 0"
                class="mt-6"
                data-testid="schools-empty"
            >
                <p class="text-body text-text">No Schools in this Tenant.</p>
                <div v-if="canManage" class="mt-4">
                    <ButtonPrimary
                        data-testid="schools-add-cta"
                        @click="openCreateForm"
                    >
                        Add School
                    </ButtonPrimary>
                </div>
            </Card>

            <Card
                v-else-if="schools.length > 0 && filteredSchools.length === 0"
                class="mt-6"
                data-testid="schools-search-empty"
            >
                <p class="text-body text-text-muted">No Schools match your search.</p>
            </Card>

            <ul
                v-else-if="filteredSchools.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="schools-list"
            >
                <li
                    v-for="school in filteredSchools"
                    :key="school.id"
                    class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    data-testid="school-row"
                >
                    <div class="min-w-0">
                        <p class="text-body font-medium text-text" data-testid="school-name">
                            {{ school.name }}
                        </p>
                        <p
                            v-if="formatLocation(school)"
                            class="text-meta text-text-muted"
                            data-testid="school-location"
                        >
                            {{ formatLocation(school) }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="school-status">
                            {{ school.is_active ? 'Active' : 'Inactive' }}
                        </p>
                    </div>
                    <div v-if="canManage" class="flex flex-wrap gap-2 sm:justify-end">
                        <ButtonOutline
                            :data-testid="`school-edit-${school.id}`"
                            @click="openEditForm(school)"
                        >
                            Edit
                        </ButtonOutline>
                        <ButtonOutline
                            :data-testid="`school-delete-${school.id}`"
                            @click="openDeleteConfirm(school)"
                        >
                            Delete
                        </ButtonOutline>
                    </div>
                </li>
            </ul>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit School' : 'Add School'"
            :close-disabled="saving"
            data-testid="schools-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                Inactive Schools stay in the Tenant but are excluded from active-only lists such as Add Pupil.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submitSchoolForm">
                <div>
                    <label class="block text-body text-text" for="school-name">Name</label>
                    <input
                        id="school-name"
                        v-model="schoolForm.name"
                        type="text"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="school-name-input"
                    >
                    <p
                        v-if="fieldErrors.name"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-name"
                    >
                        {{ fieldErrors.name }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="school-address">Address</label>
                    <input
                        id="school-address"
                        v-model="schoolForm.address"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="school-address-input"
                    >
                    <p
                        v-if="fieldErrors.address"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-address"
                    >
                        {{ fieldErrors.address }}
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-body text-text" for="school-city">City</label>
                        <input
                            id="school-city"
                            v-model="schoolForm.city"
                            type="text"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="school-city-input"
                        >
                        <p
                            v-if="fieldErrors.city"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-city"
                        >
                            {{ fieldErrors.city }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="school-county">County</label>
                        <input
                            id="school-county"
                            v-model="schoolForm.county"
                            type="text"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="school-county-input"
                        >
                        <p
                            v-if="fieldErrors.county"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-county"
                        >
                            {{ fieldErrors.county }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="school-postcode">Postcode</label>
                        <input
                            id="school-postcode"
                            v-model="schoolForm.postcode"
                            type="text"
                            maxlength="16"
                            autocomplete="postal-code"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="school-postcode-input"
                        >
                        <p
                            v-if="fieldErrors.postcode"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-postcode"
                        >
                            {{ fieldErrors.postcode }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="school-country">Country</label>
                        <input
                            id="school-country"
                            v-model="schoolForm.country"
                            type="text"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="school-country-input"
                        >
                        <p
                            v-if="fieldErrors.country"
                            class="mt-1 text-meta text-danger"
                            data-testid="error-country"
                        >
                            {{ fieldErrors.country }}
                        </p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="schoolForm.is_active"
                        type="checkbox"
                        data-testid="school-active-input"
                    >
                    Active School
                </label>
                <p
                    v-if="fieldErrors.is_active"
                    class="text-meta text-danger"
                    data-testid="error-is_active"
                >
                    {{ fieldErrors.is_active }}
                </p>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="schools-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="schools-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save School' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="schools-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedSchool !== null"
            title="Delete School"
            :close-disabled="saving"
            data-testid="schools-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete {{ selectedSchool?.name }}? This cannot be undone from this screen.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="schools-form-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="schools-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Confirm delete' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="schools-delete-cancel"
                    @click="closeForm"
                >
                    Cancel
                </ButtonSecondary>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';

const session = useSession();
const canManage = computed(() => session.role.value === 'tenant_admin');
const pageIntro = computed(() => {
    if (canManage.value) {
        return 'Create, update, and remove Schools for this Tenant. Inactive Schools remain listed here.';
    }

    return 'Schools you can access in this Tenant. Creating and editing Schools is limited to Tenant Admins.';
});

const schools = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedSchool = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    name: '',
    address: '',
    postcode: '',
    city: '',
    county: '',
    country: '',
    is_active: '',
});

const schoolForm = reactive({
    name: '',
    address: '',
    postcode: '',
    city: '',
    county: '',
    country: 'United Kingdom',
    is_active: true,
});

const filteredSchools = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return schools.value;
    }

    return schools.value.filter((school) => {
        const haystack = [
            school.name,
            school.address,
            school.city,
            school.county,
            school.postcode,
            school.country,
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');

        return haystack.includes(query);
    });
});

onMounted(async () => {
    await loadSchools();
});

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
function optionalText(value) {
    const trimmed = String(value ?? '').trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * @param {Record<string, unknown>} school
 */
function formatLocation(school) {
    return [school.address, school.city, school.county, school.postcode, school.country]
        .map((value) => String(value ?? '').trim())
        .filter((value) => value !== '')
        .join(', ');
}

function clearFieldErrors() {
    fieldErrors.name = '';
    fieldErrors.address = '';
    fieldErrors.postcode = '';
    fieldErrors.city = '';
    fieldErrors.county = '';
    fieldErrors.country = '';
    fieldErrors.is_active = '';
}

function resetSchoolForm() {
    schoolForm.name = '';
    schoolForm.address = '';
    schoolForm.postcode = '';
    schoolForm.city = '';
    schoolForm.county = '';
    schoolForm.country = 'United Kingdom';
    schoolForm.is_active = true;
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedSchool.value = null;
    resetSchoolForm();
}

function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    resetSchoolForm();
    selectedSchool.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} school
 */
function openEditForm(school) {
    if (!canManage.value) {
        return;
    }

    resetSchoolForm();
    selectedSchool.value = school;
    schoolForm.name = String(school.name ?? '');
    schoolForm.address = school.address == null ? '' : String(school.address);
    schoolForm.postcode = school.postcode == null ? '' : String(school.postcode);
    schoolForm.city = school.city == null ? '' : String(school.city);
    schoolForm.county = school.county == null ? '' : String(school.county);
    schoolForm.country = school.country == null || school.country === ''
        ? 'United Kingdom'
        : String(school.country);
    schoolForm.is_active = school.is_active !== false;
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} school
 */
function openDeleteConfirm(school) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedSchool.value = school;
    formMode.value = 'delete';
}

/**
 * @param {Record<string, unknown>} payload
 */
function applyFieldErrors(payload) {
    const errors = payload.errors;

    if (!isRecord(errors)) {
        return;
    }

    for (const [field, messages] of Object.entries(errors)) {
        if (field in fieldErrors) {
            fieldErrors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
        }
    }
}

async function loadSchools() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/schools');

        if (!response.ok) {
            loadError.value = 'Unable to load Schools.';
            schools.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        schools.value = rows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Schools.';
        schools.value = [];
    } finally {
        loading.value = false;
    }
}

async function submitSchoolForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        name: schoolForm.name.trim(),
        is_active: schoolForm.is_active,
        address: optionalText(schoolForm.address),
        postcode: optionalText(schoolForm.postcode),
        city: optionalText(schoolForm.city),
        county: optionalText(schoolForm.county),
        country: optionalText(schoolForm.country),
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate ? '/api/v1/schools' : `/api/v1/schools/${selectedSchool.value?.id}`;
    const method = isCreate ? 'POST' : 'PATCH';

    try {
        const response = await apiFetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? (isCreate ? 'Unable to create School.' : 'Unable to update School.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                schools.value = [...schools.value, saved].sort((a, b) =>
                    String(a.name ?? '').localeCompare(String(b.name ?? ''), 'en-GB'),
                );
            } else {
                schools.value = schools.value
                    .map((row) => (row.id === saved.id ? saved : row))
                    .sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'en-GB'));
            }
        } else {
            await loadSchools();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create School.' : 'Unable to update School.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const schoolId = selectedSchool.value?.id;

    try {
        const response = await apiFetch(`/api/v1/schools/${schoolId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete School.';

            return;
        }

        schools.value = schools.value.filter((row) => row.id !== schoolId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete School.';
    } finally {
        saving.value = false;
    }
}
</script>
