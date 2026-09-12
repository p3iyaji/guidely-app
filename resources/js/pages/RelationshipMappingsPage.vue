<template>
    <div data-testid="relationship-mappings-page">
        <PageHero
            eyebrow="Configuration"
            title="Relationship mappings"
            :description="pageIntro"
        >
            <template v-if="canManage && !loading && !loadError" #action>
                <ButtonPrimary
                    data-testid="relationship-mappings-add-open"
                    @click="openCreateForm"
                >
                    Add Relationship mapping
                </ButtonPrimary>
            </template>
        </PageHero>

        <CrudSearch
            id="relationship-mappings-search"
            v-model="searchQuery"
            placeholder="Search by code, label, or term"
            test-id="relationship-mappings-search"
        />

        <div v-if="loading" class="space-y-3" data-testid="relationship-mappings-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="relationship-mappings-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="mappings.length === 0"
                test-id="relationship-mappings-empty"
            >
                No Relationship mappings on this Ontology version.
                <template v-if="canManage" #actions>
                    <ButtonPrimary
                        data-testid="relationship-mappings-add-cta"
                        @click="openCreateForm"
                    >
                        Add Relationship mapping
                    </ButtonPrimary>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="mappings.length > 0 && filteredMappings.length === 0"
                test-id="relationship-mappings-search-empty"
            >
                No Relationship mappings match your search.
            </EmptyState>

            <DataTable
                v-else-if="filteredMappings.length > 0"
                test-id="relationship-mappings-list"
            >
                <template #head>
                    <tr>
                        <th class="px-4 py-3" scope="col">Code</th>
                        <th class="px-4 py-3" scope="col">Label</th>
                        <th class="px-4 py-3" scope="col">Type</th>
                        <th class="px-4 py-3" scope="col">From</th>
                        <th class="px-4 py-3" scope="col">To</th>
                        <th class="px-4 py-3" scope="col">Status</th>
                        <th class="px-4 py-3" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="mapping in filteredMappings"
                    :key="mapping.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="relationship-mapping-row"
                >
                    <td class="px-4 py-3 font-mono text-meta text-text-muted" data-testid="relationship-mapping-code">
                        {{ mapping.code }}
                    </td>
                    <td class="px-4 py-3 font-medium text-text" data-testid="relationship-mapping-label">
                        {{ mapping.label }}
                    </td>
                    <td class="px-4 py-3 text-meta text-text-muted" data-testid="relationship-mapping-type">
                        {{ mapping.relationship_type }}
                    </td>
                    <td class="px-4 py-3 text-body text-text" data-testid="relationship-mapping-from">
                        {{ domainLabel(mapping.from_domain) }}: {{ termLabel(mapping, 'from') }}
                    </td>
                    <td class="px-4 py-3 text-body text-text" data-testid="relationship-mapping-to">
                        {{ domainLabel(mapping.to_domain) }}: {{ termLabel(mapping, 'to') }}
                    </td>
                    <td class="px-4 py-3" data-testid="relationship-mapping-status">
                        {{ mapping.is_active ? 'Active' : 'Inactive' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div v-if="canManage" class="flex flex-wrap justify-end gap-2">
                            <TableAction
                                icon="edit"
                                :label="`Edit ${mapping.label}`"
                                :data-testid="`relationship-mapping-edit-${mapping.id}`"
                                @click="openEditForm(mapping)"
                            />
                            <TableAction
                                icon="delete"
                                :label="`Delete ${mapping.label}`"
                                tone="danger"
                                :data-testid="`relationship-mapping-delete-${mapping.id}`"
                                @click="openDeleteConfirm(mapping)"
                            />
                        </div>
                    </td>
                </tr>
            </DataTable>
        </template>

        <Modal
            :open="canManage && (formMode === 'create' || formMode === 'edit')"
            :title="formMode === 'edit' ? 'Edit Relationship mapping' : 'Add Relationship mapping'"
            :close-disabled="saving"
            data-testid="relationship-mappings-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                A mapping links a source term to a target term on this Ontology version.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submitMappingForm">
                <div>
                    <label class="block text-body text-text" for="relationship-mapping-code">Code</label>
                    <input
                        id="relationship-mapping-code"
                        v-model="mappingForm.code"
                        type="text"
                        required
                        maxlength="64"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="relationship-mapping-code-input"
                    >
                    <p v-if="fieldErrors.code" class="mt-1 text-meta text-danger" data-testid="error-code">
                        {{ fieldErrors.code }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="relationship-mapping-label">Label</label>
                    <input
                        id="relationship-mapping-label"
                        v-model="mappingForm.label"
                        type="text"
                        required
                        maxlength="255"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="relationship-mapping-label-input"
                    >
                    <p v-if="fieldErrors.label" class="mt-1 text-meta text-danger" data-testid="error-label">
                        {{ fieldErrors.label }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="relationship-mapping-type">Relationship type</label>
                    <input
                        id="relationship-mapping-type"
                        v-model="mappingForm.relationship_type"
                        type="text"
                        required
                        maxlength="64"
                        placeholder="need_to_provision"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="relationship-mapping-type-input"
                    >
                    <p v-if="fieldErrors.relationship_type" class="mt-1 text-meta text-danger" data-testid="error-relationship_type">
                        {{ fieldErrors.relationship_type }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-body text-text" for="relationship-mapping-from-domain">From domain</label>
                        <select
                            id="relationship-mapping-from-domain"
                            v-model="mappingForm.from_domain"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="relationship-mapping-from-domain-input"
                        >
                            <option value="" disabled>Select a domain</option>
                            <option v-for="domain in domains" :key="`from-${domain.key}`" :value="domain.key">
                                {{ domain.label }}
                            </option>
                        </select>
                        <p v-if="fieldErrors.from_domain" class="mt-1 text-meta text-danger" data-testid="error-from_domain">
                            {{ fieldErrors.from_domain }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="relationship-mapping-from-term">From term</label>
                        <select
                            id="relationship-mapping-from-term"
                            v-model="mappingForm.from_term_id"
                            required
                            :disabled="fromTerms.length === 0"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-50"
                            data-testid="relationship-mapping-from-term-input"
                        >
                            <option value="" disabled>Select a term</option>
                            <option v-for="term in fromTerms" :key="`from-${term.id}`" :value="String(term.id)">
                                {{ term.label }} ({{ term.code }})
                            </option>
                        </select>
                        <p v-if="fieldErrors.from_term_id" class="mt-1 text-meta text-danger" data-testid="error-from_term_id">
                            {{ fieldErrors.from_term_id }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-body text-text" for="relationship-mapping-to-domain">To domain</label>
                        <select
                            id="relationship-mapping-to-domain"
                            v-model="mappingForm.to_domain"
                            required
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="relationship-mapping-to-domain-input"
                        >
                            <option value="" disabled>Select a domain</option>
                            <option v-for="domain in domains" :key="`to-${domain.key}`" :value="domain.key">
                                {{ domain.label }}
                            </option>
                        </select>
                        <p v-if="fieldErrors.to_domain" class="mt-1 text-meta text-danger" data-testid="error-to_domain">
                            {{ fieldErrors.to_domain }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-body text-text" for="relationship-mapping-to-term">To term</label>
                        <select
                            id="relationship-mapping-to-term"
                            v-model="mappingForm.to_term_id"
                            required
                            :disabled="toTerms.length === 0"
                            class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-50"
                            data-testid="relationship-mapping-to-term-input"
                        >
                            <option value="" disabled>Select a term</option>
                            <option v-for="term in toTerms" :key="`to-${term.id}`" :value="String(term.id)">
                                {{ term.label }} ({{ term.code }})
                            </option>
                        </select>
                        <p v-if="fieldErrors.to_term_id" class="mt-1 text-meta text-danger" data-testid="error-to_term_id">
                            {{ fieldErrors.to_term_id }}
                        </p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="mappingForm.is_active"
                        type="checkbox"
                        data-testid="relationship-mapping-active-input"
                    >
                    Active Relationship mapping
                </label>
                <p v-if="fieldErrors.is_active" class="text-meta text-danger" data-testid="error-is_active">
                    {{ fieldErrors.is_active }}
                </p>

                <div>
                    <label class="block text-body text-text" for="relationship-mapping-sort-order">Sort order</label>
                    <input
                        id="relationship-mapping-sort-order"
                        v-model.number="mappingForm.sort_order"
                        type="number"
                        min="0"
                        max="65535"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="relationship-mapping-sort-order-input"
                    >
                    <p v-if="fieldErrors.sort_order" class="mt-1 text-meta text-danger" data-testid="error-sort_order">
                        {{ fieldErrors.sort_order }}
                    </p>
                </div>

                <p v-if="formError" class="text-body text-danger" data-testid="relationship-mappings-form-error" role="alert">
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="relationship-mappings-form-submit"
                    >
                        {{ saving ? 'Saving…' : (formMode === 'create' ? 'Save Relationship mapping' : 'Save changes') }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="relationship-mappings-form-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'delete' && selectedMapping !== null"
            title="Delete Relationship mapping"
            :close-disabled="saving"
            data-testid="relationship-mappings-delete-confirm"
            @close="closeForm"
        >
            <p class="text-body text-text">
                Delete {{ selectedMapping?.label }}? This removes the link between its source and target terms.
            </p>
            <p v-if="formError" class="mt-4 text-body text-danger" data-testid="relationship-mappings-form-error" role="alert">
                {{ formError }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <ButtonPrimary
                    :disabled="saving"
                    data-testid="relationship-mappings-delete-confirm-submit"
                    @click="submitDelete"
                >
                    {{ saving ? 'Deleting…' : 'Delete Relationship mapping' }}
                </ButtonPrimary>
                <ButtonSecondary
                    :disabled="saving"
                    data-testid="relationship-mappings-delete-cancel"
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
        return 'Create, update, and remove Relationship mappings between Ontology terms. Inactive mappings remain listed here.';
    }

    return 'Relationship mappings for this Tenant’s Ontology version. Creating and editing mappings is limited to Tenant Admins.';
});

const domains = [
    { key: 'need', label: 'Need' },
    { key: 'setting', label: 'Setting' },
    { key: 'provision', label: 'Provision' },
    { key: 'outcome', label: 'Outcome' },
    { key: 'threshold', label: 'Threshold' },
];

const domainEndpoints = {
    need: '/api/v1/ontology/need-terms',
    setting: '/api/v1/ontology/setting-terms',
    provision: '/api/v1/ontology/provision-terms',
    outcome: '/api/v1/ontology/outcome-terms',
    threshold: '/api/v1/ontology/threshold-terms',
};

const mappings = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const formMode = ref(null);
const selectedMapping = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    code: '',
    label: '',
    relationship_type: '',
    from_domain: '',
    from_term_id: '',
    to_domain: '',
    to_term_id: '',
    is_active: '',
    sort_order: '',
});

const mappingForm = reactive({
    code: '',
    label: '',
    relationship_type: '',
    from_domain: 'need',
    from_term_id: '',
    to_domain: 'provision',
    to_term_id: '',
    is_active: true,
    sort_order: 0,
});

const termOptions = reactive({
    need: [],
    setting: [],
    provision: [],
    outcome: [],
    threshold: [],
});

const fromTerms = computed(() => termOptions[mappingForm.from_domain] ?? []);
const toTerms = computed(() => termOptions[mappingForm.to_domain] ?? []);

const filteredMappings = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return mappings.value;
    }

    return mappings.value.filter((mapping) => {
        const haystack = [
            mapping.code,
            mapping.label,
            mapping.relationship_type,
            termLabel(mapping, 'from'),
            termLabel(mapping, 'to'),
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');

        return haystack.includes(query);
    });
});

onMounted(async () => {
    await Promise.all([loadMappings(), loadTermOptions()]);
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

function domainLabel(key) {
    return domains.find((domain) => domain.key === key)?.label ?? String(key ?? '');
}

/**
 * @param {Record<string, unknown>} mapping
 * @param {'from'|'to'} side
 */
function termLabel(mapping, side) {
    const nested = isRecord(mapping[`${side}_term`]) ? mapping[`${side}_term`] : null;

    if (nested && nested.label != null) {
        return String(nested.label);
    }

    const domain = String(mapping[`${side}_domain`] ?? '');
    const id = String(mapping[`${side}_term_id`] ?? '');
    const options = termOptions[domain] ?? [];
    const match = options.find((option) => String(option.id) === id);

    return match ? `${match.label} (${match.code})` : '—';
}

function clearFieldErrors() {
    fieldErrors.code = '';
    fieldErrors.label = '';
    fieldErrors.relationship_type = '';
    fieldErrors.from_domain = '';
    fieldErrors.from_term_id = '';
    fieldErrors.to_domain = '';
    fieldErrors.to_term_id = '';
    fieldErrors.is_active = '';
    fieldErrors.sort_order = '';
}

function resetMappingForm() {
    mappingForm.code = '';
    mappingForm.label = '';
    mappingForm.relationship_type = '';
    mappingForm.from_domain = 'need';
    mappingForm.from_term_id = '';
    mappingForm.to_domain = 'provision';
    mappingForm.to_term_id = '';
    mappingForm.is_active = true;
    mappingForm.sort_order = 0;
    formError.value = '';
    clearFieldErrors();
}

function closeForm() {
    formMode.value = null;
    selectedMapping.value = null;
    resetMappingForm();
}

function openCreateForm() {
    if (!canManage.value) {
        return;
    }

    resetMappingForm();
    selectedMapping.value = null;
    formMode.value = 'create';
}

/**
 * @param {Record<string, unknown>} mapping
 */
function openEditForm(mapping) {
    if (!canManage.value) {
        return;
    }

    resetMappingForm();
    selectedMapping.value = mapping;
    mappingForm.code = String(mapping.code ?? '');
    mappingForm.label = String(mapping.label ?? '');
    mappingForm.relationship_type = String(mapping.relationship_type ?? '');
    mappingForm.from_domain = String(mapping.from_domain ?? 'need');
    mappingForm.from_term_id = String(mapping.from_term_id ?? '');
    mappingForm.to_domain = String(mapping.to_domain ?? 'provision');
    mappingForm.to_term_id = String(mapping.to_term_id ?? '');
    mappingForm.is_active = mapping.is_active !== false;
    mappingForm.sort_order = Number.isFinite(Number(mapping.sort_order)) ? Number(mapping.sort_order) : 0;
    formMode.value = 'edit';
}

/**
 * @param {Record<string, unknown>} mapping
 */
function openDeleteConfirm(mapping) {
    if (!canManage.value) {
        return;
    }

    formError.value = '';
    selectedMapping.value = mapping;
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

function sortMappings(rows) {
    return [...rows].sort((a, b) => {
        const order = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);

        if (order !== 0) {
            return order;
        }

        return String(a.label ?? '').localeCompare(String(b.label ?? ''), 'en-GB');
    });
}

async function loadMappings() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/ontology/relationship-mappings');

        if (!response.ok) {
            loadError.value = 'Unable to load Relationship mappings.';
            mappings.value = [];

            return;
        }

        const payload = await response.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        mappings.value = sortMappings(rows.filter(isRecord));
    } catch {
        loadError.value = 'Unable to load Relationship mappings.';
        mappings.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadTermOptions() {
    await Promise.all(
        Object.entries(domainEndpoints).map(async ([domain, endpoint]) => {
            try {
                const response = await apiFetch(endpoint);

                if (!response.ok) {
                    termOptions[domain] = [];

                    return;
                }

                const payload = await response.json();
                const rows = Array.isArray(payload.data) ? payload.data : [];
                termOptions[domain] = rows.filter(isRecord);
            } catch {
                termOptions[domain] = [];
            }
        }),
    );
}

async function submitMappingForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        code: mappingForm.code.trim(),
        label: mappingForm.label.trim(),
        relationship_type: mappingForm.relationship_type.trim(),
        from_domain: mappingForm.from_domain,
        from_term_id: mappingForm.from_term_id,
        to_domain: mappingForm.to_domain,
        to_term_id: mappingForm.to_term_id,
        is_active: mappingForm.is_active,
        sort_order: Number.isFinite(Number(mappingForm.sort_order)) ? Number(mappingForm.sort_order) : 0,
    };
    const isCreate = formMode.value === 'create';
    const url = isCreate
        ? '/api/v1/ontology/relationship-mappings'
        : `/api/v1/ontology/relationship-mappings/${selectedMapping.value?.id}`;
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

            formError.value = payload.message ?? (isCreate ? 'Unable to create Relationship mapping.' : 'Unable to update Relationship mapping.');

            return;
        }

        const saved = payload.data;

        if (isRecord(saved) && saved.id) {
            if (isCreate) {
                mappings.value = sortMappings([...mappings.value, saved]);
            } else {
                mappings.value = sortMappings(
                    mappings.value.map((row) => (row.id === saved.id ? saved : row)),
                );
            }
        } else {
            await loadMappings();
        }

        searchQuery.value = '';
        closeForm();
    } catch {
        formError.value = isCreate ? 'Unable to create Relationship mapping.' : 'Unable to update Relationship mapping.';
    } finally {
        saving.value = false;
    }
}

async function submitDelete() {
    saving.value = true;
    formError.value = '';
    const mappingId = selectedMapping.value?.id;

    try {
        const response = await apiFetch(`/api/v1/ontology/relationship-mappings/${mappingId}`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            formError.value = payload.message ?? 'Unable to delete Relationship mapping.';

            return;
        }

        mappings.value = mappings.value.filter((row) => row.id !== mappingId);
        closeForm();
    } catch {
        formError.value = 'Unable to delete Relationship mapping.';
    } finally {
        saving.value = false;
    }
}
</script>
