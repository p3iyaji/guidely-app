<template>
    <div data-testid="settings-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">Settings</h1>
                <p class="mt-1 text-body text-text-muted">
                    {{ pageIntro }}
                </p>
            </div>
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="settings-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="settings-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <template v-else>
            <Card class="mt-6" data-testid="settings-account">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-body font-semibold text-text">Your account</h2>
                    </div>
                    <RouterLink
                        to="/profile"
                        class="inline-flex items-center justify-center rounded-md border border-border-strong bg-surface px-4 py-2 text-body font-medium text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-edit-profile"
                    >
                        Edit profile
                    </RouterLink>
                </div>
                <dl class="mt-4 space-y-3">
                    <div>
                        <dt class="text-meta text-text-muted">Name</dt>
                        <dd class="text-body text-text" data-testid="settings-account-name">
                            {{ accountName }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Email</dt>
                        <dd class="text-body text-text" data-testid="settings-account-email">
                            {{ accountEmail }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Role</dt>
                        <dd class="text-body text-text" data-testid="settings-account-role">
                            {{ roleLabel(session.role.value) }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <Card
                v-if="noTenant"
                class="mt-6"
                data-testid="settings-no-tenant"
            >
                <p class="text-body text-text">
                    This account is not attached to a Tenant organisation.
                </p>
            </Card>

            <Card
                v-else
                class="mt-6"
                data-testid="settings-organisation"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-body font-semibold text-text">Organisation</h2>
                        <p class="mt-1 text-body text-text-muted">
                            Cohort settings apply across this Tenant.
                        </p>
                    </div>
                    <ButtonOutline
                        v-if="canManage"
                        data-testid="settings-cohort-edit"
                        @click="openCohortForm"
                    >
                        Edit cohort
                    </ButtonOutline>
                </div>
                <dl class="mt-4 space-y-3">
                    <div>
                        <dt class="text-meta text-text-muted">Name</dt>
                        <dd class="text-body text-text" data-testid="settings-tenant-name">
                            {{ tenantName }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Type</dt>
                        <dd class="text-body text-text" data-testid="settings-tenant-type">
                            {{ tenantTypeLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Cohort</dt>
                        <dd class="text-body text-text" data-testid="settings-cohort-status">
                            {{ cohortStatusLabel }}
                        </dd>
                    </div>
                    <div v-if="cohortLabelDisplay">
                        <dt class="text-meta text-text-muted">Cohort label</dt>
                        <dd class="text-body text-text" data-testid="settings-cohort-label">
                            {{ cohortLabelDisplay }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <Card
                v-if="canManage && !noTenant"
                class="mt-6"
                data-testid="settings-sso"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-body font-semibold text-text">SSO</h2>
                        <p class="mt-1 text-body text-text-muted">
                            Stored IdP details only. Sign-in still uses email and password until a live IdP is connected.
                        </p>
                    </div>
                    <ButtonOutline
                        data-testid="settings-sso-edit"
                        @click="openSsoForm"
                    >
                        Edit SSO
                    </ButtonOutline>
                </div>
                <p
                    v-if="ssoLoadError"
                    class="mt-4 text-body text-danger"
                    data-testid="settings-sso-error"
                    role="alert"
                >
                    {{ ssoLoadError }}
                </p>
                <dl v-else class="mt-4 space-y-3">
                    <div>
                        <dt class="text-meta text-text-muted">Status</dt>
                        <dd class="text-body text-text" data-testid="settings-sso-status">
                            {{ ssoEnabled ? 'Enabled' : 'Disabled' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Provider</dt>
                        <dd class="text-body text-text" data-testid="settings-sso-provider">
                            {{ ssoProviderDisplay }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Entity ID</dt>
                        <dd class="text-body text-text" data-testid="settings-sso-entity">
                            {{ ssoEntityDisplay }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta text-text-muted">Client ID</dt>
                        <dd class="text-body text-text" data-testid="settings-sso-client">
                            {{ ssoClientDisplay }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <Card
                v-if="canManage && !noTenant"
                class="mt-6"
                data-testid="settings-provision-terms"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-body font-semibold text-text">Provision terms</h2>
                        <p class="mt-1 text-body text-text-muted">
                            Labels used when capturing Interventions. Inactive terms stay listed but are hidden from Capture.
                        </p>
                    </div>
                    <RouterLink
                        to="/provision-terms"
                        class="inline-flex items-center justify-center rounded-md border border-border-strong bg-surface px-4 py-2 text-body font-medium text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-provision-terms-open"
                    >
                        Manage terms
                    </RouterLink>
                </div>
            </Card>

            <Card
                v-if="canManage && !noTenant"
                class="mt-6"
                data-testid="settings-review-cycle-automation"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-body font-semibold text-text">Review Cycle automation</h2>
                        <p class="mt-1 text-body text-text-muted">
                            When the Review Cycle automation flag is on, the product can roll forward Annual Reviews daily. Default remains manual close.
                        </p>
                    </div>
                    <ButtonOutline
                        :disabled="automationRunning"
                        data-testid="settings-automation-run"
                        @click="runReviewCycleAutomation"
                    >
                        {{ automationRunning ? 'Running…' : 'Run roll-forward now' }}
                    </ButtonOutline>
                </div>
                <p
                    v-if="automationMessage"
                    class="mt-4 text-body text-text"
                    data-testid="settings-automation-message"
                    role="status"
                >
                    {{ automationMessage }}
                </p>
                <p
                    v-if="automationError"
                    class="mt-4 text-body text-danger"
                    data-testid="settings-automation-error"
                    role="alert"
                >
                    {{ automationError }}
                </p>
            </Card>
        </template>

        <Modal
            :open="canManage && formMode === 'cohort'"
            title="Edit cohort"
            :close-disabled="saving"
            data-testid="settings-cohort-form"
            @close="closeForm"
        >
            <form class="space-y-4" @submit.prevent="submitCohortForm">
                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="cohortForm.cohort_enabled"
                        type="checkbox"
                        data-testid="settings-cohort-enabled-input"
                    >
                    Enable cohort
                </label>
                <p
                    v-if="fieldErrors.cohort_enabled"
                    class="text-meta text-danger"
                    data-testid="error-cohort_enabled"
                >
                    {{ fieldErrors.cohort_enabled }}
                </p>
                <div>
                    <label class="block text-body text-text" for="settings-cohort-label">Cohort label</label>
                    <input
                        id="settings-cohort-label"
                        v-model="cohortForm.cohort_label"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-cohort-label-input"
                    >
                    <p
                        v-if="fieldErrors.cohort_label"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-cohort_label"
                    >
                        {{ fieldErrors.cohort_label }}
                    </p>
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="settings-cohort-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="settings-cohort-submit"
                    >
                        {{ saving ? 'Saving…' : 'Save cohort' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="settings-cohort-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="canManage && formMode === 'sso'"
            title="Edit SSO"
            :close-disabled="saving"
            data-testid="settings-sso-form"
            @close="closeForm"
        >
            <p class="text-body text-text-muted">
                These fields are stored on the Tenant. They do not connect a live identity provider.
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="submitSsoForm">
                <label class="flex items-center gap-2 text-body text-text">
                    <input
                        v-model="ssoForm.sso_enabled"
                        type="checkbox"
                        data-testid="settings-sso-enabled-input"
                    >
                    Enable SSO stub
                </label>
                <p
                    v-if="fieldErrors.sso_enabled"
                    class="text-meta text-danger"
                    data-testid="error-sso_enabled"
                >
                    {{ fieldErrors.sso_enabled }}
                </p>
                <div>
                    <label class="block text-body text-text" for="settings-sso-provider">Provider</label>
                    <input
                        id="settings-sso-provider"
                        v-model="ssoForm.sso_provider"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-sso-provider-input"
                    >
                    <p
                        v-if="fieldErrors.sso_provider"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-sso_provider"
                    >
                        {{ fieldErrors.sso_provider }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="settings-sso-entity">Entity ID</label>
                    <input
                        id="settings-sso-entity"
                        v-model="ssoForm.sso_entity_id"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-sso-entity-input"
                    >
                    <p
                        v-if="fieldErrors.sso_entity_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-sso_entity_id"
                    >
                        {{ fieldErrors.sso_entity_id }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="settings-sso-client">Client ID</label>
                    <input
                        id="settings-sso-client"
                        v-model="ssoForm.sso_client_id"
                        type="text"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="settings-sso-client-input"
                    >
                    <p
                        v-if="fieldErrors.sso_client_id"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-sso_client_id"
                    >
                        {{ fieldErrors.sso_client_id }}
                    </p>
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="settings-sso-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="settings-sso-submit"
                    >
                        {{ saving ? 'Saving…' : 'Save SSO' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="settings-sso-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';

const ROLE_LABELS = {
    teacher: 'Teacher',
    support_staff: 'Support Staff',
    senco: 'SENCO',
    school_leader: 'School Leader',
    tenant_admin: 'Tenant Admin',
    trust_send_lead: 'Trust SEND Lead',
    trust_executive: 'Trust Executive',
    platform_operator: 'Platform Operator',
};

const session = useSession();
const canManage = computed(() => session.role.value === 'tenant_admin');
const pageIntro = computed(() => {
    if (canManage.value) {
        return 'Your account, organisation, and SSO. Tenant Admins update cohort and SSO in a dialog.';
    }

    return 'Your account and organisation. Updating cohort and SSO is limited to Tenant Admins.';
});

const loading = ref(true);
const loadError = ref('');
const ssoLoadError = ref('');
const noTenant = ref(false);
const tenant = ref(null);
const sso = ref(null);
const formMode = ref(null);
const saving = ref(false);
const formError = ref('');
const automationRunning = ref(false);
const automationMessage = ref('');
const automationError = ref('');
const fieldErrors = reactive({
    cohort_enabled: '',
    cohort_label: '',
    sso_enabled: '',
    sso_provider: '',
    sso_entity_id: '',
    sso_client_id: '',
});

const cohortForm = reactive({
    cohort_enabled: false,
    cohort_label: '',
});

const ssoForm = reactive({
    sso_enabled: false,
    sso_provider: '',
    sso_entity_id: '',
    sso_client_id: '',
});

const accountName = computed(() => String(session.user.value?.name ?? '—'));
const accountEmail = computed(() => String(session.user.value?.email ?? '—'));
const tenantName = computed(() => String(tenant.value?.name ?? '—'));
const tenantTypeLabel = computed(() => {
    if (tenant.value?.type === 'trust') {
        return 'Trust';
    }

    if (tenant.value?.type === 'school') {
        return 'School';
    }

    return '—';
});
const cohortStatusLabel = computed(() => (tenant.value?.cohort_enabled ? 'Enabled' : 'Disabled'));
const cohortLabelDisplay = computed(() => {
    const label = String(tenant.value?.cohort_label ?? '').trim();

    return label === '' ? '' : label;
});
const ssoEnabled = computed(() => sso.value?.sso_enabled === true);
const ssoProviderDisplay = computed(() => displayOrDash(sso.value?.sso_provider));
const ssoEntityDisplay = computed(() => displayOrDash(sso.value?.sso_entity_id));
const ssoClientDisplay = computed(() => displayOrDash(sso.value?.sso_client_id));

onMounted(async () => {
    await loadSettings();
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
function displayOrDash(value) {
    const trimmed = String(value ?? '').trim();

    return trimmed === '' ? '—' : trimmed;
}

/**
 * @param {string|null|undefined} role
 */
function roleLabel(role) {
    return ROLE_LABELS[role] ?? String(role ?? '—');
}

function clearFieldErrors() {
    fieldErrors.cohort_enabled = '';
    fieldErrors.cohort_label = '';
    fieldErrors.sso_enabled = '';
    fieldErrors.sso_provider = '';
    fieldErrors.sso_entity_id = '';
    fieldErrors.sso_client_id = '';
}

function closeForm() {
    formMode.value = null;
    formError.value = '';
    clearFieldErrors();
}

function openCohortForm() {
    if (!canManage.value || tenant.value == null) {
        return;
    }

    clearFieldErrors();
    formError.value = '';
    cohortForm.cohort_enabled = tenant.value.cohort_enabled === true;
    cohortForm.cohort_label = tenant.value.cohort_label == null ? '' : String(tenant.value.cohort_label);
    formMode.value = 'cohort';
}

function openSsoForm() {
    if (!canManage.value) {
        return;
    }

    clearFieldErrors();
    formError.value = '';
    ssoForm.sso_enabled = sso.value?.sso_enabled === true;
    ssoForm.sso_provider = sso.value?.sso_provider == null ? '' : String(sso.value.sso_provider);
    ssoForm.sso_entity_id = sso.value?.sso_entity_id == null ? '' : String(sso.value.sso_entity_id);
    ssoForm.sso_client_id = sso.value?.sso_client_id == null ? '' : String(sso.value.sso_client_id);
    formMode.value = 'sso';
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

/**
 * @param {unknown} value
 */
function optionalText(value) {
    const trimmed = String(value ?? '').trim();

    return trimmed === '' ? null : trimmed;
}

async function loadSettings() {
    loading.value = true;
    loadError.value = '';
    ssoLoadError.value = '';
    noTenant.value = false;
    tenant.value = null;
    sso.value = null;

    try {
        const response = await apiFetch('/api/v1/tenant', { skipForbiddenRedirect: true });

        if (response.status === 404) {
            noTenant.value = true;

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Settings.';

            return;
        }

        const payload = await response.json();
        tenant.value = isRecord(payload.data) ? payload.data : null;

        if (tenant.value == null) {
            loadError.value = 'Unable to load Settings.';

            return;
        }

        if (canManage.value) {
            await loadSso();
        }
    } catch {
        loadError.value = 'Unable to load Settings.';
        tenant.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadSso() {
    ssoLoadError.value = '';

    try {
        const response = await apiFetch('/api/v1/tenant/sso', { skipForbiddenRedirect: true });

        if (!response.ok) {
            ssoLoadError.value = 'Unable to load SSO settings.';
            sso.value = null;

            return;
        }

        const payload = await response.json();
        sso.value = isRecord(payload.data) ? payload.data : null;
    } catch {
        ssoLoadError.value = 'Unable to load SSO settings.';
        sso.value = null;
    }
}

async function submitCohortForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        cohort_enabled: cohortForm.cohort_enabled,
        cohort_label: optionalText(cohortForm.cohort_label),
    };

    try {
        const response = await apiFetch('/api/v1/tenant', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? 'Unable to update cohort.';

            return;
        }

        if (isRecord(payload.data)) {
            tenant.value = payload.data;
        } else {
            await loadSettings();
        }

        closeForm();
    } catch {
        formError.value = 'Unable to update cohort.';
    } finally {
        saving.value = false;
    }
}

async function submitSsoForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    const body = {
        sso_enabled: ssoForm.sso_enabled,
        sso_provider: optionalText(ssoForm.sso_provider),
        sso_entity_id: optionalText(ssoForm.sso_entity_id),
        sso_client_id: optionalText(ssoForm.sso_client_id),
    };

    try {
        const response = await apiFetch('/api/v1/tenant/sso', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? 'Unable to update SSO.';

            return;
        }

        if (isRecord(payload.data)) {
            sso.value = payload.data;
        } else {
            await loadSso();
        }

        closeForm();
    } catch {
        formError.value = 'Unable to update SSO.';
    } finally {
        saving.value = false;
    }
}

async function runReviewCycleAutomation() {
    automationRunning.value = true;
    automationMessage.value = '';
    automationError.value = '';

    try {
        const response = await apiFetch('/api/v1/review-cycles/automation/run', {
            method: 'POST',
            skipForbiddenRedirect: true,
        });
        const payload = await response.json().catch(() => ({}));

        if (response.status === 403 && payload.code === 'feature_not_available') {
            automationError.value = 'Review Cycle automation is not available for this Tenant. Enable it under Feature flags.';

            return;
        }

        if (!response.ok) {
            automationError.value = payload.message ?? 'Unable to run Review Cycle automation.';

            return;
        }

        const created = Array.isArray(payload.data) ? payload.data.length : 0;
        automationMessage.value = created === 0
            ? 'No Review Cycles needed rolling forward.'
            : `Created ${created} Review Cycle${created === 1 ? '' : 's'}.`;
    } catch {
        automationError.value = 'Unable to run Review Cycle automation.';
    } finally {
        automationRunning.value = false;
    }
}
</script>
