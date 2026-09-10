<template>
    <div data-testid="roles-page">
        <PageHero
            eyebrow="Access"
            title="Role catalogue"
            description="Built-in entries correspond to product Roles. Custom entries and all Permission mappings document proposed access designs only: they cannot be assigned and never grant product access. Access is enforced from the built-in Role selected on Users. Built-in keys cannot be changed."
        >
            <template v-if="canManage && !loading && !loadError" #action>
                <ButtonPrimary
                    data-testid="roles-add-open"
                    @click="openCreateForm"
                >
                    Add catalogue role
                </ButtonPrimary>
            </template>
        </PageHero>

        <CrudSearch
            id="roles-search"
            v-model="searchQuery"
            placeholder="Search by label or key"
            test-id="roles-search"
        />

        <div v-if="loading" class="space-y-3" data-testid="roles-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="roles-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="roles.length === 0"
                test-id="roles-empty"
            >
                No Role catalogue entries in this Tenant.
                <template v-if="canManage" #actions>
                    <ButtonPrimary
                        data-testid="roles-add-cta"
                        @click="openCreateForm"
                    >
                        Add catalogue role
                    </ButtonPrimary>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="roles.length > 0 && filteredRoles.length === 0"
                test-id="roles-search-empty"
            >
                No Role catalogue entries match your search.
            </EmptyState>

            <DataTable
                v-else-if="filteredRoles.length > 0"
                test-id="roles-list"
            >
                <template #head>
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3" scope="col">Catalogue role</th>
                        <th class="hidden whitespace-nowrap px-4 py-3 sm:table-cell" scope="col">Key</th>
                        <th class="whitespace-nowrap px-4 py-3" scope="col">Kind</th>
                        <th class="hidden whitespace-nowrap px-4 py-3 lg:table-cell" scope="col">Documented permissions</th>
                        <th class="w-20 whitespace-nowrap px-4 py-3 text-right" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="role in filteredRoles"
                    :key="role.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="role-row"
                >
                    <td class="px-4 py-3 align-top">
                        <p class="font-medium text-text" data-testid="role-label">{{ role.label }}</p>
                        <p class="mt-0.5 font-mono text-meta text-text-muted sm:hidden" data-testid="role-key-mobile">
                            {{ role.key }}
                        </p>
                    </td>
                    <td class="hidden px-4 py-3 align-top font-mono text-meta text-text-muted sm:table-cell" data-testid="role-key">
                        {{ role.key }}
                    </td>
                    <td class="px-4 py-3 align-top">
                        <span
                            class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-label font-medium"
                            :class="role.is_system ? 'bg-primary-soft text-primary' : 'bg-surface-muted text-text-muted'"
                            data-testid="role-kind"
                        >
                            {{ role.is_system ? 'Built-in' : 'Custom · not assignable' }}
                        </span>
                    </td>
                    <td class="hidden px-4 py-3 align-top lg:table-cell">
                        <div v-if="permissionCount(role) > 0" data-testid="role-permissions">
                            <p class="text-meta font-medium text-text-muted">{{ permissionSummary(role) }}</p>
                            <span class="mt-1 inline-flex items-center whitespace-nowrap rounded-full border border-border bg-surface-muted px-2 py-0.5 text-meta font-medium text-text-muted">
                                {{ permissionCount(role) }} mapped
                            </span>
                        </div>
                        <span v-else class="text-meta text-text-muted" data-testid="role-permissions-empty">—</span>
                    </td>
                    <td class="px-4 py-3 align-middle">
                        <div v-if="canManage" class="flex items-center justify-end gap-1">
                            <TableAction
                                icon="edit"
                                :label="`Edit ${role.label}`"
                                :data-testid="`role-edit-${role.id}`"
                                @click="openEditForm(role)"
                            />
                            <TableAction
                                icon="delete"
                                :label="`Delete ${role.label}`"
                                tone="danger"
                                :data-testid="`role-delete-${role.id}`"
                                @click="openDeleteConfirm(role)"
                            />
                        </div>
                    </td>
                </tr>
            </DataTable>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit catalogue role' : 'Add catalogue role'"
            :close-disabled="saving"
            data-testid="roles-form"
            @close="closeForm"
        >
            <p class="mb-4 text-body text-text-muted" data-testid="roles-catalogue-notice">
                Catalogue entries and Permission mappings are documentation only. They do not change product access, and custom roles cannot be assigned to Users.
            </p>
            <form class="space-y-4" @submit.prevent="submitRoleForm">
                <div>
                    <label class="block text-body text-text" for="role-key">Key</label>
                    <input
                        id="role-key"
                        v-model="roleForm.key"
                        type="text"
                        required
                        :disabled="keyLocked"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:bg-surface-muted disabled:text-text-muted"
                        data-testid="role-key-input"
                    >
                    <p
                        v-if="fieldErrors.key"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-key"
                    >
                        {{ fieldErrors.key }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="role-label">Label</label>
                    <input
                        id="role-label"
                        v-model="roleForm.label"
                        type="text"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="role-label-input"
                    >
                    <p
                        v-if="fieldErrors.label"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-label"
                    >
                        {{ fieldErrors.label }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="role-description">Description</label>
                    <textarea
                        id="role-description"
                        v-model="roleForm.description"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="role-description-input"
                    />
                </div>
                <fieldset>
                    <legend class="text-body text-text">Documented Permission mappings</legend>
                    <div class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border border-border p-3">
                        <label
                            v-for="permission in permissions"
                            :key="permission.id"
                            class="flex items-start gap-2 text-body text-text"
                        >
                            <input
                                v-model="roleForm.permission_ids"
                                type="checkbox"
                                :value="permission.id"
                                :data-testid="`role-permission-${permission.id}`"
                            >
                            <span>
                                {{ permission.label }}
                                <span class="text-meta text-text-muted">{{ permission.key }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="roles-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="roles-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save catalogue role' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="roles-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedRole !== null"
            title="Delete catalogue role"
            :close-disabled="saving"
            data-testid="roles-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete the catalogue entry {{ selectedRole?.label }}? This removes catalogue metadata only and does not change product access.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="roles-form-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="roles-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Confirm delete' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="roles-delete-cancel"
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
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import CrudSearch from '../shared/ui/CrudSearch.vue';
import DataTable from '../shared/ui/DataTable.vue';
import EmptyState from '../shared/ui/EmptyState.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';
import PageHero from '../shared/ui/PageHero.vue';
import TableAction from '../shared/ui/TableAction.vue';

const session = useSession();
const canManage = computed(() => session.role.value === 'tenant_admin');

const roles = ref([]);
const permissions = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedRole = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    key: '',
    label: '',
    description: '',
    permission_ids: '',
});

const roleForm = reactive({
    key: '',
    label: '',
    description: '',
    permission_ids: [],
});

const keyLocked = computed(() => formMode.value === 'edit' && selectedRole.value?.is_system === true);

const filteredRoles = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return roles.value;
    }

    return roles.value.filter((role) => {
        const haystack = [role.label, role.key, role.description]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');

        return haystack.includes(query);
    });
});

onMounted(async () => {
    await loadPage();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {Record<string, unknown>} role
 */
function permissionCount(role) {
    return Array.isArray(role.permissions) ? role.permissions.length : 0;
}

/**
 * @param {Record<string, unknown>} role
 */
function permissionSummary(role) {
    const assigned = Array.isArray(role.permissions) ? role.permissions : [];
    const labels = assigned
        .map((permission) => (isRecord(permission) ? String(permission.label ?? '') : ''))
        .filter((label) => label !== '');

    if (labels.length === 0) {
        return '';
    }

    return labels.join(', ');
}

function clearFieldErrors() {
    fieldErrors.key = '';
    fieldErrors.label = '';
    fieldErrors.description = '';
    fieldErrors.permission_ids = '';
}

function resetRoleForm() {
    roleForm.key = '';
    roleForm.label = '';
    roleForm.description = '';
    roleForm.permission_ids = [];
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedRole.value = null;
    resetRoleForm();
}

function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    resetRoleForm();
    selectedRole.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} role
 */
function openEditForm(role) {
    if (!canManage.value) {
        return;
    }

    resetRoleForm();
    selectedRole.value = role;
    roleForm.key = String(role.key ?? '');
    roleForm.label = String(role.label ?? '');
    roleForm.description = role.description == null ? '' : String(role.description);
    roleForm.permission_ids = Array.isArray(role.permission_ids)
        ? role.permission_ids.map((id) => String(id))
        : [];
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} role
 */
function openDeleteConfirm(role) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedRole.value = role;
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

async function loadPage() {
    loading.value = true;
    loadError.value = '';

    if (!canManage.value) {
        loadError.value = 'You don’t have access.';
        roles.value = [];
        loading.value = false;

        return;
    }

    try {
        const [rolesResponse, permissionsResponse] = await Promise.all([
            apiFetch('/api/v1/roles', { skipForbiddenRedirect: true }),
            apiFetch('/api/v1/permissions', { skipForbiddenRedirect: true }),
        ]);

        if (rolesResponse.status === 403 || permissionsResponse.status === 403) {
            loadError.value = 'You don’t have access.';
            roles.value = [];
            permissions.value = [];

            return;
        }

        if (!rolesResponse.ok || !permissionsResponse.ok) {
            loadError.value = 'Unable to load the Role catalogue.';
            roles.value = [];
            permissions.value = [];

            return;
        }

        const rolesPayload = await rolesResponse.json();
        const permissionsPayload = await permissionsResponse.json();
        const roleRows = Array.isArray(rolesPayload.data) ? rolesPayload.data : [];
        const permissionRows = Array.isArray(permissionsPayload.data) ? permissionsPayload.data : [];

        roles.value = roleRows.filter(isRecord);
        permissions.value = permissionRows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load the Role catalogue.';
        roles.value = [];
        permissions.value = [];
    } finally {
        loading.value = false;
    }
}

async function submitRoleForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        key: roleForm.key.trim(),
        label: roleForm.label.trim(),
        description: roleForm.description.trim() === '' ? null : roleForm.description.trim(),
        permission_ids: roleForm.permission_ids,
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate ? '/api/v1/roles' : `/api/v1/roles/${selectedRole.value?.id}`;
    const method = isCreate ? 'POST' : 'PATCH';

    try {
        const response = await apiFetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            skipForbiddenRedirect: true,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message
                ?? (isCreate ? 'Unable to create the catalogue role.' : 'Unable to update the catalogue role.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                roles.value = [...roles.value, saved];
            } else {
                roles.value = roles.value.map((row) => (row.id === saved.id ? saved : row));
            }
        } else {
            await loadPage();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate
            ? 'Unable to create the catalogue role.'
            : 'Unable to update the catalogue role.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const roleId = selectedRole.value?.id;

    try {
        const response = await apiFetch(`/api/v1/roles/${roleId}`, {
            method: 'DELETE',
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete the catalogue role.';

            return;
        }

        roles.value = roles.value.filter((row) => row.id !== roleId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete the catalogue role.';
    } finally {
        saving.value = false;
    }
}
</script>
