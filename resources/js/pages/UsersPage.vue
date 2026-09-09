<template>
    <div data-testid="users-page">
        <PageHero
            eyebrow="Access"
            title="Users"
            description="Create and update Tenant Users, assign Roles and Schools, reset passwords, and deactivate accounts. Users are not deleted."
        >
            <template v-if="!loading && !loadError" #action>
                <ButtonPrimary
                    data-testid="users-add-open"
                    @click="openCreateForm"
                >
                    Add User
                </ButtonPrimary>
            </template>
        </PageHero>

        <CrudSearch
            id="users-search"
            v-model="searchQuery"
            placeholder="Search by name, email, or Role"
            test-id="users-search"
        />

        <div v-if="loading" class="space-y-3" data-testid="users-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="users-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="users.length === 0"
                test-id="users-empty"
            >
                No Users in this Tenant.
                <template #actions>
                    <ButtonPrimary
                        data-testid="users-add-cta"
                        @click="openCreateForm"
                    >
                        Add User
                    </ButtonPrimary>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="users.length > 0 && filteredUsers.length === 0"
                test-id="users-search-empty"
            >
                No Users match your search.
            </EmptyState>

            <DataTable
                v-else-if="filteredUsers.length > 0"
                test-id="users-list"
            >
                <template #head>
                    <tr>
                        <th class="px-4 py-3" scope="col">User</th>
                        <th class="px-4 py-3" scope="col">Role</th>
                        <th class="px-4 py-3" scope="col">Status</th>
                        <th class="hidden px-4 py-3 lg:table-cell" scope="col">Schools</th>
                        <th class="px-4 py-3" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="row in filteredUsers"
                    :key="row.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="user-row"
                >
                    <td class="px-4 py-3">
                        <p class="font-medium text-text" data-testid="user-name">{{ row.name }}</p>
                        <p class="text-meta text-text-muted" data-testid="user-email">{{ row.email }}</p>
                    </td>
                    <td class="px-4 py-3 text-text-muted" data-testid="user-role">
                        {{ roleLabel(row.role) }}
                    </td>
                    <td class="px-4 py-3" data-testid="user-status">
                        {{ row.deactivated_at ? 'Deactivated' : 'Active' }}
                    </td>
                    <td class="hidden px-4 py-3 text-meta text-text-muted lg:table-cell" data-testid="user-schools">
                        {{ schoolNamesFor(row) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <TableAction
                                icon="edit"
                                :label="`Edit ${row.name}`"
                                :disabled="Boolean(row.deactivated_at)"
                                :data-testid="`user-edit-${row.id}`"
                                @click="openEditForm(row)"
                            />
                            <TableAction
                                icon="key"
                                :label="`Reset password for ${row.name}`"
                                :disabled="Boolean(row.deactivated_at)"
                                :data-testid="`user-password-${row.id}`"
                                @click="openPasswordForm(row)"
                            />
                            <TableAction
                                icon="deactivate"
                                :label="`Deactivate ${row.name}`"
                                tone="danger"
                                :disabled="Boolean(row.deactivated_at)"
                                :data-testid="`user-deactivate-${row.id}`"
                                @click="openDeactivateConfirm(row)"
                            />
                        </div>
                    </td>
                </tr>
            </DataTable>
        </template>

        <Modal
            :open="formMode === 'create' || formMode === 'edit'"
            :title="formMode === 'edit' ? 'Edit User' : 'Add User'"
            :close-disabled="saving"
            data-testid="users-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                Assign a Role and optional Schools. Platform Operator cannot be assigned from this Tenant.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submitUserForm">
                <div>
                    <label class="block text-body text-text" for="user-name">Name</label>
                    <input
                        id="user-name"
                        v-model="userForm.name"
                        type="text"
                        required
                        autocomplete="name"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-name-input"
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
                    <label class="block text-body text-text" for="user-email">Email</label>
                    <input
                        id="user-email"
                        v-model="userForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-email-input"
                    >
                    <p
                        v-if="fieldErrors.email"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-email"
                    >
                        {{ fieldErrors.email }}
                    </p>
                </div>
                <div v-if="formMode === 'create'">
                    <label class="block text-body text-text" for="user-password">Password</label>
                    <input
                        id="user-password"
                        v-model="userForm.password"
                        type="password"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-password-input"
                    >
                    <p
                        v-if="fieldErrors.password"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password"
                    >
                        {{ fieldErrors.password }}
                    </p>
                </div>
                <div v-if="formMode === 'create'">
                    <label class="block text-body text-text" for="user-password-confirm">Confirm password</label>
                    <input
                        id="user-password-confirm"
                        v-model="userForm.password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-password-confirm-input"
                    >
                    <p
                        v-if="fieldErrors.password_confirmation"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password_confirmation"
                    >
                        {{ fieldErrors.password_confirmation }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="user-external-id">External id (optional)</label>
                    <input
                        id="user-external-id"
                        v-model="userForm.external_id"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-external-id-input"
                    >
                    <p
                        v-if="fieldErrors.external_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-external_id"
                    >
                        {{ fieldErrors.external_id }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="user-role">Role</label>
                    <select
                        id="user-role"
                        v-model="userForm.role"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-role-input"
                    >
                        <option
                            v-for="option in assignableRoles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.role"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-role"
                    >
                        {{ fieldErrors.role }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" id="user-schools-label" for="user-schools-trigger">
                        Schools
                    </label>
                    <p class="mt-1 text-body text-text-muted">
                        Optional. Leave unselected if the User should not be limited to named Schools. Choose more than one School from the list.
                    </p>
                    <p
                        v-if="schools.length === 0"
                        class="mt-2 text-body text-text-muted"
                        data-testid="users-schools-empty"
                    >
                        No Schools yet. Add a School first if this User should be scoped.
                    </p>
                    <div
                        v-else
                        class="relative mt-1"
                        data-testid="user-schools-input"
                    >
                        <button
                            id="user-schools-trigger"
                            type="button"
                            class="flex min-h-11 w-full items-center justify-between gap-2 rounded-md border border-border bg-surface px-3 py-2 text-left text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            aria-labelledby="user-schools-label"
                            aria-haspopup="listbox"
                            :aria-expanded="schoolsMenuOpen ? 'true' : 'false'"
                            data-testid="user-schools-trigger"
                            @click="schoolsMenuOpen = !schoolsMenuOpen"
                        >
                            <span
                                class="min-w-0 flex-1 truncate"
                                :class="userForm.school_ids.length === 0 ? 'text-text-muted' : ''"
                            >
                                {{ selectedSchoolsSummary }}
                            </span>
                            <span class="shrink-0 text-text-muted" aria-hidden="true">▾</span>
                        </button>
                        <ul
                            v-if="schoolsMenuOpen"
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-border bg-surface py-1 shadow-[0_1px_3px_rgba(31,41,55,0.08)]"
                            role="listbox"
                            aria-labelledby="user-schools-label"
                            aria-multiselectable="true"
                            data-testid="user-schools-listbox"
                        >
                            <li
                                v-for="school in schools"
                                :key="school.id"
                                role="option"
                                :aria-selected="userForm.school_ids.includes(school.id) ? 'true' : 'false'"
                                class="cursor-pointer px-3 py-2 text-body text-text hover:bg-surface-muted"
                                :class="userForm.school_ids.includes(school.id) ? 'bg-primary-soft' : ''"
                                :data-testid="`user-school-${school.id}`"
                                @click="toggleSchool(school.id)"
                            >
                                {{ school.name }}
                            </li>
                        </ul>
                    </div>
                    <p
                        v-if="fieldErrors.school_ids"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-school_ids"
                    >
                        {{ fieldErrors.school_ids }}
                    </p>
                </div>

                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="users-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="users-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save User' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="users-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="formMode === 'password' && selectedUser !== null"
            title="Reset password"
            :close-disabled="saving"
            data-testid="users-password-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                Set a new password for {{ selectedUser?.name }}. Existing sessions for that User will end.
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="submitPassword">
                <div>
                    <label class="block text-body text-text" for="user-reset-password">New password</label>
                    <input
                        id="user-reset-password"
                        v-model="passwordForm.password"
                        type="password"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-reset-password-input"
                    >
                    <p
                        v-if="fieldErrors.password"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password"
                    >
                        {{ fieldErrors.password }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="user-reset-password-confirm">Confirm password</label>
                    <input
                        id="user-reset-password-confirm"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="user-reset-password-confirm-input"
                    >
                    <p
                        v-if="fieldErrors.password_confirmation"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password_confirmation"
                    >
                        {{ fieldErrors.password_confirmation }}
                    </p>
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="users-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="users-password-submit"
                    >
                        {{ saving ? 'Saving…' : 'Save password' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="users-password-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="formMode === 'deactivate' && selectedUser !== null"
            title="Deactivate User"
            :close-disabled="saving"
            data-testid="users-deactivate-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Deactivate {{ selectedUser?.name }} ({{ selectedUser?.email }})? They will no longer be able to sign in.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="users-form-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="users-deactivate-confirm-submit"
                    @click="submitDeactivate"
                >
                    {{ saving ? 'Deactivating…' : 'Confirm deactivate' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="users-deactivate-cancel"
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
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import CrudSearch from '../shared/ui/CrudSearch.vue';
import DataTable from '../shared/ui/DataTable.vue';
import EmptyState from '../shared/ui/EmptyState.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';
import PageHero from '../shared/ui/PageHero.vue';
import TableAction from '../shared/ui/TableAction.vue';

const ROLE_OPTIONS = [
    { value: 'teacher', label: 'Teacher' },
    { value: 'support_staff', label: 'Support Staff' },
    { value: 'senco', label: 'SENCO' },
    { value: 'school_leader', label: 'School Leader' },
    { value: 'tenant_admin', label: 'Tenant Admin' },
    { value: 'trust_send_lead', label: 'Trust SEND Lead', trust: true },
    { value: 'trust_executive', label: 'Trust Executive', trust: true },
];

const users = ref([]);
const schools = ref([]);
const trustDashboardEnabled = ref(false);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const schoolsMenuOpen = ref(false);
const selectedUser = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    external_id: '',
    role: '',
    school_ids: '',
});

const userForm = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    external_id: '',
    role: 'teacher',
    school_ids: [],
});

const passwordForm = reactive({
    password: '',
    password_confirmation: '',
});

const assignableRoles = computed(() =>
    ROLE_OPTIONS.filter((option) => !option.trust || trustDashboardEnabled.value),
);

const selectedSchoolsSummary = computed(() => {
    if (userForm.school_ids.length === 0) {
        return 'Select Schools (optional)';
    }

    const names = userForm.school_ids
        .map((id) => schools.value.find((school) => school.id === id)?.name)
        .filter((name) => typeof name === 'string' && name !== '');

    if (names.length === 0) {
        return `${userForm.school_ids.length} School${userForm.school_ids.length === 1 ? '' : 's'} selected`;
    }

    return names.join(', ');
});

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return users.value;
    }

    return users.value.filter((row) => {
        const name = String(row.name ?? '').toLowerCase();
        const email = String(row.email ?? '').toLowerCase();
        const role = roleLabel(row.role).toLowerCase();

        return name.includes(query) || email.includes(query) || role.includes(query);
    });
});

onMounted(async () => {
    await Promise.all([loadUsers(), loadSchools(), loadFeatureFlags()]);
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {string|null|undefined} role
 */
function roleLabel(role) {
    return ROLE_OPTIONS.find((option) => option.value === role)?.label ?? String(role ?? '—');
}

/**
 * @param {{ school_ids?: unknown }} row
 */
function schoolNamesFor(row) {
    const ids = Array.isArray(row.school_ids) ? row.school_ids : [];

    if (ids.length === 0) {
        return 'No Schools assigned';
    }

    const names = ids
        .map((id) => schools.value.find((school) => school.id === id)?.name)
        .filter((name) => typeof name === 'string' && name !== '');

    return names.length > 0 ? names.join(', ') : `${ids.length} School${ids.length === 1 ? '' : 's'}`;
}

function clearFieldErrors() {
    fieldErrors.name = '';
    fieldErrors.email = '';
    fieldErrors.password = '';
    fieldErrors.password_confirmation = '';
    fieldErrors.external_id = '';
    fieldErrors.role = '';
    fieldErrors.school_ids = '';
}

function resetUserForm() {
    userForm.name = '';
    userForm.email = '';
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.external_id = '';
    userForm.role = 'teacher';
    userForm.school_ids = [];
    schoolsMenuOpen.value = false;
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedUser.value = null;
    schoolsMenuOpen.value = false;
    resetUserForm();
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
}

function openCreateForm() {
    resetUserForm();
    selectedUser.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} row
 */
function openEditForm(row) {
    resetUserForm();
    selectedUser.value = row;
    userForm.name = String(row.name ?? '');
    userForm.email = String(row.email ?? '');
    userForm.external_id = row.external_id == null ? '' : String(row.external_id);
    userForm.role = typeof row.role === 'string' ? row.role : 'teacher';
    userForm.school_ids = Array.isArray(row.school_ids) ? [...row.school_ids] : [];
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} row
 */
function openPasswordForm(row) {
    clearFieldErrors();
    formError.value = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    selectedUser.value = row;
    formMode.value = 'password';
}

/**
 * @param {string} schoolId
 */
function toggleSchool(schoolId) {
    const selected = userForm.school_ids;
    const index = selected.indexOf(schoolId);

    if (index === -1) {
        selected.push(schoolId);

        return;
    }

    selected.splice(index, 1);
}

/**
 * @param {Record<string, unknown>} row
 */
function openDeactivateConfirm(row) {
    clearFieldErrors();
    formError.value = '';
    selectedUser.value = row;
    formMode.value = 'deactivate';
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

function replaceUser(updated) {
    users.value = users.value
        .map((row) => (row.id === updated.id ? updated : row))
        .sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'en-GB'));
}

async function loadUsers() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/users');

        if (!response.ok) {
            loadError.value = 'Unable to load Users.';
            users.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        users.value = rows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Users.';
        users.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadSchools() {
    try {
        const response = await apiFetch('/api/v1/schools');

        if (!response.ok) {
            schools.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        schools.value = rows.filter(isRecord);
    } catch {
        schools.value = [];
    }
}

async function loadFeatureFlags() {
    try {
        const response = await apiFetch('/api/v1/tenant/feature-flags', {
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            trustDashboardEnabled.value = false;

            return;
        }

        const payload = await response.json();
        trustDashboardEnabled.value = payload?.data?.trust_dashboard === true;
    } catch {
        trustDashboardEnabled.value = false;
    }
}

async function submitUserForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    if (formMode.value === 'create' && userForm.password !== userForm.password_confirmation) {
        fieldErrors.password_confirmation = 'Password confirmation does not match.';
        saving.value = false;

        return;
    }

    const body = {
        name: userForm.name.trim(),
        email: userForm.email.trim(),
        role: userForm.role,
        school_ids: [...userForm.school_ids],
        external_id: userForm.external_id.trim() === '' ? null : userForm.external_id.trim(),
    };

    if (formMode.value === 'create') {
        body.password = userForm.password;
    }

    const isCreate = formMode.value === 'create';
    const url = isCreate ? '/api/v1/users' : `/api/v1/users/${selectedUser.value?.id}`;
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

            formError.value = payload.message ?? (isCreate ? 'Unable to create User.' : 'Unable to update User.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                users.value = [...users.value, saved].sort((a, b) =>
                    String(a.name ?? '').localeCompare(String(b.name ?? ''), 'en-GB'),
                );
            } else {
                replaceUser(saved);
            }
        } else {
            await loadUsers();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create User.' : 'Unable to update User.';
    } finally {
        saving.value = false;
    }
}

async function submitPassword() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    if (passwordForm.password !== passwordForm.password_confirmation) {
        fieldErrors.password_confirmation = 'Password confirmation does not match.';
        saving.value = false;

        return;
    }

    try {
        const response = await apiFetch(`/api/v1/users/${selectedUser.value?.id}/password`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: passwordForm.password }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? 'Unable to reset password.';

            return;
        }

        closeForm();
    } catch {
        formError.value = 'Unable to reset password.';
    } finally {
        saving.value = false;
    }
}

async function submitDeactivate() {
    saving.value = true;
    formError.value = '';

    try {
        const response = await apiFetch(`/api/v1/users/${selectedUser.value?.id}/deactivate`, {
            method: 'POST',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            formError.value = payload.message ?? 'Unable to deactivate User.';

            return;
        }

        const updated = payload.data;

        if (isRecord(updated) && updated.id) {
            replaceUser(updated);
        } else {
            await loadUsers();
        }

        closeForm();
    } catch {
        formError.value = 'Unable to deactivate User.';
    } finally {
        saving.value = false;
    }
}
</script>
