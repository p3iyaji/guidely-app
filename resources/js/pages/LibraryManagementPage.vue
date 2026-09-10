<template>
    <div data-testid="library-management-page">
        <h1 class="text-heading font-semibold text-text">Library management</h1>
        <p class="mt-1 text-body text-text-muted">
            Publish Ontology and Rule Library versions, then pin them to a Tenant.
        </p>

        <div v-if="loading" class="mt-8 space-y-3" data-testid="library-management-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card v-else-if="loadError" class="mt-8" data-testid="library-management-error">
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            <div class="mt-4">
                <ButtonSecondary type="button" @click="loadData">Try again</ButtonSecondary>
            </div>
        </Card>

        <Card v-else-if="tenants.length === 0" class="mt-8" data-testid="library-management-empty">
            <p class="text-body text-text-muted">
                No Tenants are available for library management.
            </p>
        </Card>

        <form
            v-else
            class="mt-8 space-y-6"
            data-testid="library-management-form"
            @submit.prevent="publishLibraries"
        >
            <Card>
                <label class="block text-body font-medium text-text" for="library-tenant">
                    Tenant
                </label>
                <select
                    id="library-tenant"
                    v-model="selectedTenantId"
                    class="mt-2 w-full max-w-xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    data-testid="library-tenant-select"
                    required
                    @change="resetSelection"
                >
                    <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                        {{ tenant.name }} ({{ tenant.type }})
                    </option>
                </select>
            </Card>

            <div v-if="selectedTenant" class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <h2 class="text-body font-semibold text-text">Current Ontology pin</h2>
                    <p
                        v-if="selectedTenant.current_ontology_version"
                        class="mt-2 text-body text-text"
                        data-testid="current-ontology-pin"
                    >
                        {{ versionLabel(selectedTenant.current_ontology_version) }}
                        <span class="text-text-muted">
                            · {{ formatStatus(selectedTenant.current_ontology_version.status) }}
                        </span>
                    </p>
                    <p v-else class="mt-2 text-body text-text-muted" data-testid="current-ontology-pin">
                        Not pinned
                    </p>
                </Card>

                <Card>
                    <h2 class="text-body font-semibold text-text">Current Rule Library pin</h2>
                    <p
                        v-if="selectedTenant.current_rule_library_version"
                        class="mt-2 text-body text-text"
                        data-testid="current-rule-library-pin"
                    >
                        {{ versionLabel(selectedTenant.current_rule_library_version) }}
                        <span class="text-text-muted">
                            · {{ formatStatus(selectedTenant.current_rule_library_version.status) }}
                        </span>
                    </p>
                    <p
                        v-else
                        class="mt-2 text-body text-text-muted"
                        data-testid="current-rule-library-pin"
                    >
                        Not pinned
                    </p>
                </Card>
            </div>

            <Card>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <label class="block text-body font-medium text-text" for="ontology-version">
                            Ontology version
                        </label>
                        <select
                            id="ontology-version"
                            v-model="ontologyVersionId"
                            class="mt-2 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="ontology-version-select"
                            :aria-describedby="fieldErrors.ontology_version_id ? 'ontology-version-error' : undefined"
                        >
                            <option value="">Keep current pin</option>
                            <option
                                v-for="version in ontologyVersions"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ versionLabel(version) }} · {{ formatStatus(version.status) }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.ontology_version_id"
                            id="ontology-version-error"
                            class="mt-2 text-body text-danger"
                            role="alert"
                        >
                            {{ fieldErrors.ontology_version_id }}
                        </p>
                        <p v-else-if="ontologyVersions.length === 0" class="mt-2 text-body text-text-muted">
                            No Ontology versions are available.
                        </p>
                    </div>

                    <div>
                        <label class="block text-body font-medium text-text" for="rule-library-version">
                            Rule Library version
                        </label>
                        <select
                            id="rule-library-version"
                            v-model="ruleLibraryVersionId"
                            class="mt-2 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="rule-library-version-select"
                            :aria-describedby="fieldErrors.rule_library_version_id ? 'rule-library-version-error' : undefined"
                        >
                            <option value="">Keep current pin</option>
                            <option
                                v-for="version in ruleLibraryVersions"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ versionLabel(version) }} · {{ formatStatus(version.status) }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.rule_library_version_id"
                            id="rule-library-version-error"
                            class="mt-2 text-body text-danger"
                            role="alert"
                        >
                            {{ fieldErrors.rule_library_version_id }}
                        </p>
                        <p
                            v-else-if="ruleLibraryVersions.length === 0"
                            class="mt-2 text-body text-text-muted"
                        >
                            No Rule Library versions are available.
                        </p>
                    </div>
                </div>

                <p v-if="publishError" class="mt-4 text-body text-danger" role="alert">
                    {{ publishError }}
                </p>
                <p
                    v-if="publishSuccess"
                    class="mt-4 text-body text-success"
                    data-testid="library-publish-success"
                    aria-live="polite"
                >
                    {{ publishSuccess }}
                </p>

                <div class="mt-6">
                    <ButtonPrimary
                        type="submit"
                        :disabled="publishing || !hasSelection"
                        data-testid="library-publish-submit"
                    >
                        {{ publishing ? 'Publishing…' : 'Publish and pin selected versions' }}
                    </ButtonPrimary>
                </div>
            </Card>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const tenants = ref([]);
const ontologyVersions = ref([]);
const ruleLibraryVersions = ref([]);
const selectedTenantId = ref('');
const ontologyVersionId = ref('');
const ruleLibraryVersionId = ref('');
const loading = ref(true);
const publishing = ref(false);
const loadError = ref('');
const publishError = ref('');
const publishSuccess = ref('');
const fieldErrors = reactive({
    ontology_version_id: '',
    rule_library_version_id: '',
});

const selectedTenant = computed(
    () => tenants.value.find((tenant) => tenant.id === selectedTenantId.value) ?? null,
);
const hasSelection = computed(
    () => ontologyVersionId.value !== '' || ruleLibraryVersionId.value !== '',
);

onMounted(loadData);

async function loadData() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/operator/library-management');
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            loadError.value = payload.message ?? 'Unable to load library management.';
            return;
        }

        tenants.value = payload.data?.tenants ?? [];
        ontologyVersions.value = payload.data?.ontology_versions ?? [];
        ruleLibraryVersions.value = payload.data?.rule_library_versions ?? [];

        if (!tenants.value.some((tenant) => tenant.id === selectedTenantId.value)) {
            selectedTenantId.value = tenants.value[0]?.id ?? '';
        }
    } catch {
        loadError.value = 'Unable to load library management.';
    } finally {
        loading.value = false;
    }
}

function resetSelection() {
    ontologyVersionId.value = '';
    ruleLibraryVersionId.value = '';
    publishError.value = '';
    publishSuccess.value = '';
    clearFieldErrors();
}

function clearFieldErrors() {
    fieldErrors.ontology_version_id = '';
    fieldErrors.rule_library_version_id = '';
}

async function publishLibraries() {
    if (!selectedTenant.value || !hasSelection.value) {
        return;
    }

    publishing.value = true;
    publishError.value = '';
    publishSuccess.value = '';
    clearFieldErrors();

    try {
        const response = await apiFetch(
            `/api/v1/operator/library-management/${selectedTenant.value.id}`,
            {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ontology_version_id: ontologyVersionId.value || null,
                    rule_library_version_id: ruleLibraryVersionId.value || null,
                }),
            },
        );
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            fieldErrors.ontology_version_id = payload.errors?.ontology_version_id?.[0] ?? '';
            fieldErrors.rule_library_version_id = payload.errors?.rule_library_version_id?.[0] ?? '';
            publishError.value = payload.message ?? 'Unable to publish selected libraries.';
            return;
        }

        const queuedCount = payload.data?.result?.queued_count ?? 0;
        publishSuccess.value = `Libraries updated for ${selectedTenant.value.name}. ${queuedCount} SRE re-evaluation${queuedCount === 1 ? '' : 's'} queued.`;
        ontologyVersionId.value = '';
        ruleLibraryVersionId.value = '';
        await loadData();
    } catch {
        publishError.value = 'Unable to publish selected libraries.';
    } finally {
        publishing.value = false;
    }
}

function versionLabel(version) {
    return `${version.label} (${version.code})`;
}

function formatStatus(status) {
    return String(status ?? '').replaceAll('_', ' ');
}
</script>
