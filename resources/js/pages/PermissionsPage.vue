<template>
    <div data-testid="permissions-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">Permissions</h1>
                <p class="mt-1 text-body text-text-muted">
                    Built-in Permissions describe product capabilities. Edit labels and grouping, or create custom
                    Permissions for this Tenant and assign them to Roles. Built-in keys cannot be changed.
                </p>
            </div>
            <ButtonSecondary
                v-if="canManage && !loading && !loadError"
                data-testid="permissions-add-open"
                @click="openCreateForm"
            >
                Add Permission
            </ButtonSecondary>
        </div>

        <div class="mt-6 max-w-md">
            <label class="block text-body text-text" for="permissions-search">Search</label>
            <input
                id="permissions-search"
                v-model="searchQuery"
                type="search"
                placeholder="Search by label, key, or group"
                class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="permissions-search"
            >
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="permissions-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="permissions-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <template v-else>
            <Card
                v-if="permissions.length === 0"
                class="mt-6"
                data-testid="permissions-empty"
            >
                <p class="text-body text-text">No Permissions in this Tenant.</p>
                <div v-if="canManage" class="mt-4">
                    <ButtonPrimary
                        data-testid="permissions-add-cta"
                        @click="openCreateForm"
                    >
                        Add Permission
                    </ButtonPrimary>
                </div>
            </Card>

            <Card
                v-else-if="permissions.length > 0 && filteredPermissions.length === 0"
                class="mt-6"
                data-testid="permissions-search-empty"
            >
                <p class="text-body text-text-muted">No Permissions match your search.</p>
            </Card>

            <ul
                v-else-if="filteredPermissions.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="permissions-list"
            >
                <li
                    v-for="permission in filteredPermissions"
                    :key="permission.id"
                    class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    data-testid="permission-row"
                >
                    <div class="min-w-0">
                        <p class="text-body font-medium text-text" data-testid="permission-label">
                            {{ permission.label }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="permission-key">
                            {{ permission.key }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="permission-kind">
                            {{ permission.is_system ? 'Built-in' : 'Custom' }}
                            <span v-if="permission.group"> · {{ permission.group }}</span>
                        </p>
                    </div>
                    <div v-if="canManage" class="flex flex-wrap gap-2 sm:justify-end">
                        <ButtonOutline
                            :data-testid="`permission-edit-${permission.id}`"
                            @click="openEditForm(permission)"
                        >
                            Edit
                        </ButtonOutline>
                        <ButtonOutline
                            :data-testid="`permission-delete-${permission.id}`"
                            @click="openDeleteConfirm(permission)"
                        >
                            Delete
                        </ButtonOutline>
                    </div>
                </li>
            </ul>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit Permission' : 'Add Permission'"
            :close-disabled="saving"
            data-testid="permissions-form"
            @close="closeForm"
        >
            <form class="space-y-4" @submit.prevent="submitPermissionForm">
                <div>
                    <label class="block text-body text-text" for="permission-key">Key</label>
                    <input
                        id="permission-key"
                        v-model="permissionForm.key"
                        type="text"
                        required
                        :disabled="keyLocked"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:bg-surface-muted disabled:text-text-muted"
                        data-testid="permission-key-input"
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
                    <label class="block text-body text-text" for="permission-label">Label</label>
                    <input
                        id="permission-label"
                        v-model="permissionForm.label"
                        type="text"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="permission-label-input"
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
                    <label class="block text-body text-text" for="permission-group">Group</label>
                    <input
                        id="permission-group"
                        v-model="permissionForm.group"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="permission-group-input"
                    >
                </div>
                <div>
                    <label class="block text-body text-text" for="permission-description">Description</label>
                    <textarea
                        id="permission-description"
                        v-model="permissionForm.description"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="permission-description-input"
                    />
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="permissions-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="permissions-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save Permission' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="permissions-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedPermission !== null"
            title="Delete Permission"
            :close-disabled="saving"
            data-testid="permissions-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete {{ selectedPermission?.label }}? This cannot be undone from this screen.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="permissions-form-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="permissions-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Confirm delete' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="permissions-delete-cancel"
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

const permissions = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedPermission = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    key: '',
    label: '',
    description: '',
    group: '',
});

const permissionForm = reactive({
    key: '',
    label: '',
    description: '',
    group: '',
});

const keyLocked = computed(() => formMode.value === 'edit' && selectedPermission.value?.is_system === true);

const filteredPermissions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return permissions.value;
    }

    return permissions.value.filter((permission) => {
        const haystack = [permission.label, permission.key, permission.group, permission.description]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');

        return haystack.includes(query);
    });
});

onMounted(async () => {
    await loadPermissions();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

function clearFieldErrors() {
    fieldErrors.key = '';
    fieldErrors.label = '';
    fieldErrors.description = '';
    fieldErrors.group = '';
}

function resetPermissionForm() {
    permissionForm.key = '';
    permissionForm.label = '';
    permissionForm.description = '';
    permissionForm.group = 'Custom';
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedPermission.value = null;
    resetPermissionForm();
}

function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    resetPermissionForm();
    selectedPermission.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} permission
 */
function openEditForm(permission) {
    if (!canManage.value) {
        return;
    }

    resetPermissionForm();
    selectedPermission.value = permission;
    permissionForm.key = String(permission.key ?? '');
    permissionForm.label = String(permission.label ?? '');
    permissionForm.description = permission.description == null ? '' : String(permission.description);
    permissionForm.group = permission.group == null ? '' : String(permission.group);
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} permission
 */
function openDeleteConfirm(permission) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedPermission.value = permission;
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

async function loadPermissions() {
    loading.value = true;
    loadError.value = '';

    if (!canManage.value) {
        loadError.value = 'You don’t have access.';
        permissions.value = [];
        loading.value = false;

        return;
    }

    try {
        const response = await apiFetch('/api/v1/permissions', {
            skipForbiddenRedirect: true,
        });

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';
            permissions.value = [];

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Permissions.';
            permissions.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        permissions.value = rows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Permissions.';
        permissions.value = [];
    } finally {
        loading.value = false;
    }
}

async function submitPermissionForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        key: permissionForm.key.trim(),
        label: permissionForm.label.trim(),
        description: permissionForm.description.trim() === '' ? null : permissionForm.description.trim(),
        group: permissionForm.group.trim() === '' ? null : permissionForm.group.trim(),
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate ? '/api/v1/permissions' : `/api/v1/permissions/${selectedPermission.value?.id}`;
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
                ?? (isCreate ? 'Unable to create Permission.' : 'Unable to update Permission.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                permissions.value = [...permissions.value, saved];
            } else {
                permissions.value = permissions.value.map((row) => (row.id === saved.id ? saved : row));
            }
        } else {
            await loadPermissions();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create Permission.' : 'Unable to update Permission.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const permissionId = selectedPermission.value?.id;

    try {
        const response = await apiFetch(`/api/v1/permissions/${permissionId}`, {
            method: 'DELETE',
            skipForbiddenRedirect: true,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete Permission.';

            return;
        }

        permissions.value = permissions.value.filter((row) => row.id !== permissionId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete Permission.';
    } finally {
        saving.value = false;
    }
}
</script>
