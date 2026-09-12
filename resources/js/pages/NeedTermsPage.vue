<template>
    <div data-testid="need-terms-page">
        <PageHero
            eyebrow="Configuration"
            title="Need terms"
            :description="pageIntro"
        >
            <template v-if="canManage && !loading && !loadError" #action>
                <ButtonPrimary
                    data-testid="need-terms-add-open"
                    @click="openCreateForm"
                >
                    Add Need term
                </ButtonPrimary>
            </template>
        </PageHero>

        <CrudSearch
            id="need-terms-search"
            v-model="searchQuery"
            placeholder="Search by code or label"
            test-id="need-terms-search"
        />

        <div v-if="loading" class="space-y-3" data-testid="need-terms-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="need-terms-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="terms.length === 0"
                test-id="need-terms-empty"
            >
                No Need terms on this Ontology version.
                <template v-if="canManage" #actions>
                    <ButtonPrimary
                        data-testid="need-terms-add-cta"
                        @click="openCreateForm"
                    >
                        Add Need term
                    </ButtonPrimary>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="terms.length > 0 && filteredTerms.length === 0"
                test-id="need-terms-search-empty"
            >
                No Need terms match your search.
            </EmptyState>

            <DataTable
                v-else-if="filteredTerms.length > 0"
                test-id="need-terms-list"
            >
                <template #head>
                    <tr>
                        <th class="px-4 py-3" scope="col">Term</th>
                        <th class="px-4 py-3" scope="col">Code</th>
                        <th class="px-4 py-3" scope="col">Status</th>
                        <th class="px-4 py-3" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="term in filteredTerms"
                    :key="term.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="need-term-row"
                >
                    <td class="px-4 py-3 font-medium text-text" data-testid="need-term-label">
                        {{ term.label }}
                    </td>
                    <td class="px-4 py-3 font-mono text-meta text-text-muted" data-testid="need-term-code">
                        {{ term.code }}
                    </td>
                    <td class="px-4 py-3" data-testid="need-term-status">
                        {{ term.is_active ? 'Active' : 'Inactive' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div v-if="canManage" class="flex flex-wrap justify-end gap-2">
                            <TableAction
                                icon="edit"
                                :label="`Edit ${term.label}`"
                                :data-testid="`need-term-edit-${term.id}`"
                                @click="openEditForm(term)"
                            />
                            <TableAction
                                icon="delete"
                                :label="`Delete ${term.label}`"
                                tone="danger"
                                :data-testid="`need-term-delete-${term.id}`"
                                @click="openDeleteConfirm(term)"
                            />
                        </div>
                    </td>
                </tr>
            </DataTable>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit Need term' : 'Add Need term'"
            :close-disabled="saving"
            data-testid="need-terms-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                Inactive terms stay listed here but are hidden from Pupil need pickers.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submitTermForm">
                <div>
                    <label class="block text-body text-text" for="need-term-code">Code</label>
                    <input
                        id="need-term-code"
                        v-model="termForm.code"
                        type="text"
                        required
                        maxlength="64"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="need-term-code-input"
                    >
                    <p
                        v-if="fieldErrors.code"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-code"
                    >
                        {{ fieldErrors.code }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="need-term-label">Label</label>
                    <input
                        id="need-term-label"
                        v-model="termForm.label"
                        type="text"
                        required
                        maxlength="255"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="need-term-label-input"
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
                    <label class="block text-body text-text" for="need-term-sort-order">Sort order</label>
                    <input
                        id="need-term-sort-order"
                        v-model.number="termForm.sort_order"
                        type="number"
                        min="0"
                        max="65535"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="need-term-sort-order-input"
                    >
                    <p
                        v-if="fieldErrors.sort_order"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-sort_order"
                    >
                        {{ fieldErrors.sort_order }}
                    </p>
                </div>
                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="termForm.is_active"
                        type="checkbox"
                        data-testid="need-term-active-input"
                    >
                    Active Need term
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
                    data-testid="need-terms-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="need-terms-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save Need term' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="need-terms-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedTerm !== null"
            title="Delete Need term"
            :close-disabled="saving"
            data-testid="need-terms-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete {{ selectedTerm?.label }}? Terms used on Pupils cannot be deleted.
            </p>
            <p
                v-if="formError"
                class="mt-4 text-body text-danger"
                data-testid="need-terms-form-error"
                role="alert"
            >
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="need-terms-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Delete Need term' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="need-terms-delete-cancel"
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
const pageIntro = computed(() => {
    if (canManage.value) {
        return 'Create, update, and remove Need terms for Pupils. Inactive terms remain listed here.';
    }

    return 'Need terms for this Tenant’s Ontology version. Creating and editing terms is limited to Tenant Admins.';
});

const terms = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedTerm = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    code: '',
    label: '',
    sort_order: '',
    is_active: '',
});

const termForm = reactive({
    code: '',
    label: '',
    sort_order: 0,
    is_active: true,
});

const filteredTerms = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return terms.value;
    }

    return terms.value.filter((term) => {
        const haystack = [term.code, term.label]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');

        return haystack.includes(query);
    });
});

onMounted(async () => {
    await loadTerms();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

function clearFieldErrors() {
    fieldErrors.code = '';
    fieldErrors.label = '';
    fieldErrors.sort_order = '';
    fieldErrors.is_active = '';
}

function resetTermForm() {
    termForm.code = '';
    termForm.label = '';
    termForm.sort_order = 0;
    termForm.is_active = true;
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedTerm.value = null;
    resetTermForm();
}

function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    resetTermForm();
    selectedTerm.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} term
 */
function openEditForm(term) {
    if (!canManage.value) {
        return;
    }

    resetTermForm();
    selectedTerm.value = term;
    termForm.code = String(term.code ?? '');
    termForm.label = String(term.label ?? '');
    termForm.sort_order = Number.isFinite(Number(term.sort_order)) ? Number(term.sort_order) : 0;
    termForm.is_active = term.is_active !== false;
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} term
 */
function openDeleteConfirm(term) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedTerm.value = term;
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

function sortTerms(rows) {
    return [...rows].sort((a, b) => {
        const order = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);

        if (order !== 0) {
            return order;
        }

        return String(a.label ?? '').localeCompare(String(b.label ?? ''), 'en-GB');
    });
}

async function loadTerms() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/ontology/need-terms');

        if (!response.ok) {
            loadError.value = 'Unable to load Need terms.';
            terms.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        terms.value = rows.filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Need terms.';
        terms.value = [];
    } finally {
        loading.value = false;
    }
}

async function submitTermForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        code: termForm.code.trim(),
        label: termForm.label.trim(),
        sort_order: Number.isFinite(Number(termForm.sort_order)) ? Number(termForm.sort_order) : 0,
        is_active: termForm.is_active,
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate
        ? '/api/v1/ontology/need-terms'
        : `/api/v1/ontology/need-terms/${selectedTerm.value?.id}`;
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

            formError.value = payload.message ?? (isCreate ? 'Unable to create Need term.' : 'Unable to update Need term.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                terms.value = sortTerms([...terms.value, saved]);
            } else {
                terms.value = sortTerms(
                    terms.value.map((row) => (row.id === saved.id ? saved : row)),
                );
            }
        } else {
            await loadTerms();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create Need term.' : 'Unable to update Need term.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const termId = selectedTerm.value?.id;

    try {
        const response = await apiFetch(`/api/v1/ontology/need-terms/${termId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete Need term.';

            return;
        }

        terms.value = terms.value.filter((row) => row.id !== termId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete Need term.';
    } finally {
        saving.value = false;
    }
}
</script>
