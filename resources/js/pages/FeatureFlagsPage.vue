<template>
    <div data-testid="feature-flags-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Feature flags</h1>
            <p class="mt-1 text-body text-text-muted">
                Enable or disable Tenant surfaces. Flags hide capability for this Tenant; they do not remove it from the product.
            </p>
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="feature-flags-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="feature-flags-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <ul
            v-else
            class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
            data-testid="feature-flags-list"
        >
            <li
                v-for="flag in flags"
                :key="flag.key"
                class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                data-testid="feature-flag-row"
            >
                <div class="min-w-0">
                    <p class="text-body font-medium text-text" :data-testid="`flag-label-${flag.key}`">
                        {{ flag.label }}
                    </p>
                    <p class="text-meta text-text-muted" :data-testid="`flag-key-${flag.key}`">
                        {{ flag.key }}
                    </p>
                    <p class="mt-1 text-meta text-text-muted">
                        {{ flag.enabled ? 'Enabled' : 'Disabled' }}
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-body text-text">
                    <input
                        type="checkbox"
                        :checked="flag.enabled"
                        :disabled="savingKey === flag.key"
                        :data-testid="`flag-toggle-${flag.key}`"
                        @change="toggleFlag(flag.key, $event)"
                    >
                    {{ flag.enabled ? 'On' : 'Off' }}
                </label>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const FLAG_LABELS = {
    trust_dashboard: 'Trust Dashboard',
    connectors: 'Connectors',
    advanced_documentation_packs: 'Advanced documentation packs',
    review_cycle_automation: 'Review Cycle automation',
    portfolio_benchmarking: 'Portfolio benchmarking',
    compliance_alerts: 'Compliance alerts',
    safeguarding_ingest: 'Safeguarding ingest',
};

const session = useSession();
const loading = ref(true);
const loadError = ref('');
const flags = ref([]);
const savingKey = ref('');

onMounted(async () => {
    await loadFlags();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

function mapFlags(payload) {
    const data = isRecord(payload?.data) ? payload.data : {};

    return Object.keys(FLAG_LABELS).map((key) => ({
        key,
        label: FLAG_LABELS[key],
        enabled: data[key] === true,
    }));
}

async function loadFlags() {
    loading.value = true;
    loadError.value = '';

    if (session.role.value !== 'tenant_admin') {
        loadError.value = 'You don’t have access.';
        flags.value = [];
        loading.value = false;

        return;
    }

    try {
        const response = await apiFetch('/api/v1/tenant/feature-flags', {
            skipForbiddenRedirect: true,
        });

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';
            flags.value = [];

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load feature flags.';
            flags.value = [];

            return;
        }

        const payload = await response.json();
        flags.value = mapFlags(payload);
    } catch {
        loadError.value = 'Unable to load feature flags.';
        flags.value = [];
    } finally {
        loading.value = false;
    }
}

/**
 * @param {string} key
 * @param {Event} event
 */
async function toggleFlag(key, event) {
    const input = event.target;
    const enabled = input instanceof HTMLInputElement ? input.checked : false;
    const previous = flags.value.find((flag) => flag.key === key)?.enabled === true;

    savingKey.value = key;

    try {
        const response = await apiFetch('/api/v1/tenant/feature-flags', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key, enabled }),
            skipForbiddenRedirect: true,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (input instanceof HTMLInputElement) {
                input.checked = previous;
            }

            loadError.value = payload.message ?? 'Unable to update feature flags.';

            return;
        }

        flags.value = mapFlags(payload);
        loadError.value = '';
    } catch {
        if (input instanceof HTMLInputElement) {
            input.checked = previous;
        }

        loadError.value = 'Unable to update feature flags.';
    } finally {
        savingKey.value = '';
    }
}
</script>
