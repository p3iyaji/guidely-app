<template>
    <div data-testid="pilot-toolkit-page">
        <h1 class="text-heading font-semibold text-text">Pilot toolkit</h1>
        <p class="mt-1 text-body text-text-muted">
            Bootstrap and Pilot support surfaces for operators and Tenant Admins. Vue menus never authorise APIs.
        </p>

        <section
            v-if="isPlatformOperator"
            class="mt-8"
            data-testid="pilot-create-tenant"
        >
            <Card>
                <h2 class="text-body font-semibold text-text">Create Pilot Tenant</h2>
                <p class="mt-1 text-body text-text-muted">
                    Platform Operator bootstrap with sample or empty cohort. No pupil rows are seeded here.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="createTenant">
                    <div>
                        <label class="block text-body text-text" for="pilot-tenant-name">Name</label>
                        <input
                            id="pilot-tenant-name"
                            v-model="createForm.name"
                            type="text"
                            required
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="pilot-tenant-name"
                        />
                    </div>
                    <div>
                        <label class="block text-body text-text" for="pilot-tenant-type">Type</label>
                        <select
                            id="pilot-tenant-type"
                            v-model="createForm.type"
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="pilot-tenant-type"
                        >
                            <option value="school">School</option>
                            <option value="trust">Trust</option>
                        </select>
                    </div>
                    <fieldset>
                        <legend class="text-body text-text">Cohort mode</legend>
                        <label class="mt-2 flex items-center gap-2 text-body text-text">
                            <input
                                v-model="createForm.cohort_mode"
                                type="radio"
                                value="sample"
                                data-testid="pilot-cohort-sample"
                            />
                            Sample (cohort enabled with Pilot label)
                        </label>
                        <label class="mt-2 flex items-center gap-2 text-body text-text">
                            <input
                                v-model="createForm.cohort_mode"
                                type="radio"
                                value="empty"
                                data-testid="pilot-cohort-empty"
                            />
                            Empty (cohort disabled)
                        </label>
                    </fieldset>
                    <p
                        v-if="createError"
                        class="text-body text-danger"
                        data-testid="pilot-create-error"
                        role="alert"
                    >
                        {{ createError }}
                    </p>
                    <p
                        v-if="createSuccess"
                        class="text-body text-text"
                        data-testid="pilot-create-success"
                    >
                        {{ createSuccess }}
                    </p>
                    <ButtonPrimary
                        type="submit"
                        :disabled="creating"
                        data-testid="pilot-create-submit"
                    >
                        {{ creating ? 'Creating…' : 'Create Tenant' }}
                    </ButtonPrimary>
                </form>
            </Card>
        </section>

        <section
            v-if="isTenantAdmin"
            class="mt-8 space-y-6"
            data-testid="pilot-tenant-admin-tools"
        >
            <Card>
                <h2 class="text-body font-semibold text-text">Import Template</h2>
                <p class="mt-1 text-body text-text-muted">
                    Headers-only CSV placeholder. Full parse and upload lands later.
                </p>
                <p
                    v-if="templateError"
                    class="mt-2 text-body text-danger"
                    data-testid="pilot-template-error"
                    role="alert"
                >
                    {{ templateError }}
                </p>
                <div class="mt-4">
                    <ButtonSecondary
                        :disabled="downloadingTemplate"
                        data-testid="pilot-download-template"
                        @click="downloadTemplate"
                    >
                        {{ downloadingTemplate ? 'Downloading…' : 'Download Import Template' }}
                    </ButtonSecondary>
                </div>
            </Card>

            <Card>
                <h2 class="text-body font-semibold text-text">Disclaimer pack</h2>
                <p class="mt-1 text-body text-text-muted">
                    In-product Pilot disclaimers in UK English.
                </p>
                <p
                    v-if="disclaimerError"
                    class="mt-2 text-body text-danger"
                    role="alert"
                >
                    {{ disclaimerError }}
                </p>
                <ul
                    v-else
                    class="mt-4 space-y-4"
                    data-testid="pilot-disclaimer-list"
                >
                    <li
                        v-for="item in disclaimers"
                        :key="item.id"
                        :data-testid="`pilot-disclaimer-${item.id}`"
                    >
                        <h3 class="text-body font-medium text-text">{{ item.heading }}</h3>
                        <p class="mt-1 text-body text-text-muted">{{ item.body }}</p>
                    </li>
                </ul>
            </Card>

            <Card>
                <h2 class="text-body font-semibold text-text">Success metrics</h2>
                <p class="mt-1 text-body text-text-muted">
                    Export a stub JSON file with placeholder Pilot kickoff columns.
                </p>
                <p
                    v-if="metricsError"
                    class="mt-2 text-body text-danger"
                    data-testid="pilot-metrics-error"
                    role="alert"
                >
                    {{ metricsError }}
                </p>
                <div class="mt-4">
                    <ButtonSecondary
                        :disabled="exportingMetrics"
                        data-testid="pilot-export-metrics"
                        @click="exportMetrics"
                    >
                        {{ exportingMetrics ? 'Exporting…' : 'Export success-metrics stub' }}
                    </ButtonSecondary>
                </div>
            </Card>
        </section>

        <Card
            v-if="!isPlatformOperator && !isTenantAdmin"
            class="mt-8"
            data-testid="pilot-toolkit-unavailable"
        >
            <p class="text-body text-text-muted">
                Pilot toolkit actions are available to Platform Operators and Tenant Admins only.
            </p>
        </Card>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';

const session = useSession();

const isPlatformOperator = computed(
    () => session.role.value === 'platform_operator' && session.user.value?.tenant_id == null,
);
const isTenantAdmin = computed(() => session.role.value === 'tenant_admin');

const createForm = reactive({
    name: '',
    type: 'school',
    cohort_mode: 'sample',
});
const creating = ref(false);
const createError = ref('');
const createSuccess = ref('');

const disclaimers = ref([]);
const disclaimerError = ref('');
const templateError = ref('');
const metricsError = ref('');
const downloadingTemplate = ref(false);
const exportingMetrics = ref(false);

onMounted(async () => {
    if (!isTenantAdmin.value) {
        return;
    }

    try {
        const response = await apiFetch('/api/v1/pilot/disclaimers');
        if (!response.ok) {
            disclaimerError.value = 'Unable to load disclaimer pack.';
            return;
        }

        const payload = await response.json();
        disclaimers.value = payload.data?.items ?? [];
    } catch {
        disclaimerError.value = 'Unable to load disclaimer pack.';
    }
});

async function createTenant() {
    creating.value = true;
    createError.value = '';
    createSuccess.value = '';

    try {
        const response = await apiFetch('/api/v1/pilot/tenants', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...createForm }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            createError.value = payload.message ?? 'Unable to create Pilot Tenant.';
            return;
        }

        const name = payload.data?.name ?? '';
        const id = payload.data?.id ?? '';
        createSuccess.value = `Created Tenant ${name} (${id}). Next: php artisan guidely:onboard-school ${id}`;
        createForm.name = '';
    } catch {
        createError.value = 'Unable to create Pilot Tenant.';
    } finally {
        creating.value = false;
    }
}

async function downloadTemplate() {
    downloadingTemplate.value = true;
    templateError.value = '';

    try {
        const response = await apiFetch('/api/v1/pilot/import-template');
        if (!response.ok) {
            templateError.value = 'Unable to download Import Template.';
            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'guidely-import-template-placeholder.csv';
        link.click();
        URL.revokeObjectURL(url);
    } catch {
        templateError.value = 'Unable to download Import Template.';
    } finally {
        downloadingTemplate.value = false;
    }
}

async function exportMetrics() {
    exportingMetrics.value = true;
    metricsError.value = '';

    try {
        const response = await apiFetch('/api/v1/pilot/success-metrics');
        if (!response.ok) {
            metricsError.value = 'Unable to export success-metrics stub.';
            return;
        }

        const payload = await response.json();
        const blob = new Blob([JSON.stringify(payload.data, null, 2)], {
            type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'guidely-success-metrics-stub.json';
        link.click();
        URL.revokeObjectURL(url);
    } catch {
        metricsError.value = 'Unable to export success-metrics stub.';
    } finally {
        exportingMetrics.value = false;
    }
}
</script>
