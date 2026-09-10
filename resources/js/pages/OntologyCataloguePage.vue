<template>
    <div data-testid="ontology-catalogue-page">
        <PageHero
            eyebrow="Configuration"
            title="Ontology and Rule catalogue"
            description="Read the active Ontology and Rule Library assigned to this Tenant."
        >
            <template #action>
                <RouterLink
                    to="/provision-terms"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-4 py-2 text-body font-semibold text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    data-testid="ontology-provision-terms-link"
                >
                    Manage Provision terms
                </RouterLink>
            </template>
        </PageHero>

        <CrudSearch
            id="ontology-catalogue-search"
            v-model="searchQuery"
            label="Search the active catalogue"
            placeholder="Search by code or label"
            test-id="ontology-catalogue-search"
        />

        <div
            class="mb-5 flex flex-wrap gap-2 border-b border-border pb-3"
            role="tablist"
            aria-label="Catalogue sections"
            data-testid="ontology-catalogue-tabs"
        >
            <button
                v-for="section in sections"
                :id="`catalogue-tab-${section.key}`"
                :key="section.key"
                type="button"
                role="tab"
                class="min-h-11 rounded-md border px-3 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring"
                :class="activeSection === section.key
                    ? 'border-primary bg-primary-soft font-semibold text-primary'
                    : 'border-border bg-surface text-text-muted hover:bg-surface-muted'"
                :aria-selected="activeSection === section.key ? 'true' : 'false'"
                :aria-controls="`catalogue-panel-${section.key}`"
                :data-testid="`ontology-tab-${section.key}`"
                @click="activeSection = section.key"
            >
                {{ section.label }}
                <span class="sr-only"> catalogue</span>
            </button>
        </div>

        <section
            :id="`catalogue-panel-${currentSection.key}`"
            role="tabpanel"
            :aria-labelledby="`catalogue-tab-${currentSection.key}`"
            :data-testid="`ontology-panel-${currentSection.key}`"
        >
            <div class="mb-4">
                <h2 class="text-subheading font-semibold text-text">{{ currentSection.label }}</h2>
                <p class="mt-1 text-body text-text-muted">{{ currentSection.description }}</p>
            </div>

            <div
                v-if="currentState.loading"
                class="space-y-3"
                :data-testid="`ontology-${currentSection.key}-loading`"
            >
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <div
                v-else-if="currentState.error"
                class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
                :data-testid="`ontology-${currentSection.key}-error`"
                role="alert"
            >
                <p>{{ currentState.error }}</p>
                <button
                    type="button"
                    class="mt-3 min-h-11 rounded-md border border-danger/40 px-3 py-2 font-semibold focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :data-testid="`ontology-${currentSection.key}-retry`"
                    @click="loadSection(currentSection)"
                >
                    Try again
                </button>
            </div>

            <EmptyState
                v-else-if="currentState.rows.length === 0"
                :test-id="`ontology-${currentSection.key}-empty`"
            >
                No {{ currentSection.emptyLabel }} are available on the active version.
            </EmptyState>

            <EmptyState
                v-else-if="filteredRows.length === 0"
                :test-id="`ontology-${currentSection.key}-search-empty`"
            >
                No {{ currentSection.emptyLabel }} match your search.
            </EmptyState>

            <ul v-else class="grid gap-4 lg:grid-cols-2" :data-testid="`ontology-${currentSection.key}-list`">
                <li v-for="row in filteredRows" :key="row.id">
                    <Card class="h-full" :data-testid="`ontology-${currentSection.key}-card`">
                        <div class="flex flex-col gap-1">
                            <h3 class="text-body font-semibold text-text">{{ row.label || 'Unlabelled entry' }}</h3>
                            <p class="font-mono text-meta text-text-muted">{{ row.code || 'No code' }}</p>
                        </div>

                        <dl v-if="currentSection.key === 'relationships'" class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Source</dt>
                                <dd class="mt-1 text-body text-text">
                                    {{ relationshipTermLabel(row.from_term, row.from_domain) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Target</dt>
                                <dd class="mt-1 text-body text-text">
                                    {{ relationshipTermLabel(row.to_term, row.to_domain) }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Relationship type</dt>
                                <dd class="mt-1 text-body text-text">{{ humanise(row.relationship_type) }}</dd>
                            </div>
                        </dl>

                        <dl v-else-if="currentSection.key === 'rules'" class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Dimension</dt>
                                <dd class="mt-1 text-body text-text">{{ humanise(row.dimension) }}</dd>
                            </div>
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Category</dt>
                                <dd class="mt-1 text-body text-text">{{ humanise(row.category) }}</dd>
                            </div>
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Evaluation</dt>
                                <dd class="mt-1 text-body text-text">{{ humanise(row.evaluation?.type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Result</dt>
                                <dd class="mt-1 text-body text-text">{{ humanise(row.outcome?.result) }}</dd>
                            </div>
                            <div v-if="row.outcome?.gap_code" class="sm:col-span-2">
                                <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Gap code</dt>
                                <dd class="mt-1 font-mono text-meta text-text">{{ row.outcome.gap_code }}</dd>
                            </div>
                        </dl>
                    </Card>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { apiFetch } from '../api/client';
import Card from '../shared/ui/Card.vue';
import CrudSearch from '../shared/ui/CrudSearch.vue';
import EmptyState from '../shared/ui/EmptyState.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import PageHero from '../shared/ui/PageHero.vue';

const sections = [
    {
        key: 'needs',
        label: 'Need',
        emptyLabel: 'Need terms',
        endpoint: '/api/v1/ontology/need-terms',
        description: 'Need classifications available to the Tenant.',
    },
    {
        key: 'settings',
        label: 'Setting',
        emptyLabel: 'Setting terms',
        endpoint: '/api/v1/ontology/setting-terms',
        description: 'Settings used to describe where Evidence was observed.',
    },
    {
        key: 'outcomes',
        label: 'Outcome',
        emptyLabel: 'Outcome terms',
        endpoint: '/api/v1/ontology/outcome-terms',
        description: 'Outcome markers available on the active Ontology version.',
    },
    {
        key: 'thresholds',
        label: 'Threshold',
        emptyLabel: 'Threshold terms',
        endpoint: '/api/v1/ontology/threshold-terms',
        description: 'Thresholds used by review and evaluation flows.',
    },
    {
        key: 'relationships',
        label: 'Relationships',
        emptyLabel: 'relationship mappings',
        endpoint: '/api/v1/ontology/relationship-mappings',
        description: 'How terms in the active Ontology relate to one another.',
    },
    {
        key: 'rules',
        label: 'Rules',
        emptyLabel: 'Rules',
        endpoint: '/api/v1/ontology/rules',
        description: 'Read-only rules from the active Rule Library version.',
    },
];

const states = reactive(Object.fromEntries(
    sections.map((section) => [section.key, { rows: [], loading: true, error: '' }]),
));
const activeSection = ref(sections[0].key);
const searchQuery = ref('');

const currentSection = computed(() => (
    sections.find((section) => section.key === activeSection.value) ?? sections[0]
));
const currentState = computed(() => states[currentSection.value.key]);
const filteredRows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return currentState.value.rows;
    }

    return currentState.value.rows.filter((row) => (
        [row.code, row.label]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ')
            .includes(query)
    ));
});

onMounted(async () => {
    await Promise.all(sections.map(loadSection));
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {{ key: string, label: string, endpoint: string }} section
 */
async function loadSection(section) {
    const state = states[section.key];
    state.loading = true;
    state.error = '';

    try {
        const response = await apiFetch(section.endpoint);

        if (!response.ok) {
            state.rows = [];
            state.error = `Unable to load ${section.label.toLowerCase()} catalogue.`;

            return;
        }

        const payload = await response.json();
        state.rows = Array.isArray(payload.data) ? payload.data.filter(isRecord) : [];
    } catch {
        state.rows = [];
        state.error = `Unable to load ${section.label.toLowerCase()} catalogue.`;
    } finally {
        state.loading = false;
    }
}

/**
 * @param {unknown} value
 */
function humanise(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return '—';
    }

    return text
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

/**
 * @param {{ label?: string, type?: string }|null|undefined} term
 * @param {unknown} fallbackType
 */
function relationshipTermLabel(term, fallbackType) {
    const label = String(term?.label ?? '').trim() || 'Unknown term';
    const type = humanise(term?.type ?? fallbackType);

    return `${label} · ${type}`;
}
</script>
