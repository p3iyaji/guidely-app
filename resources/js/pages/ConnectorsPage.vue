<template>
    <div data-testid="connectors-page">
        <FeatureFlaggedEmpty v-if="flagUnavailable" />

        <template v-else>
            <h1 class="text-heading font-semibold text-text">Connectors</h1>
            <p class="mt-1 text-body text-text-muted">
                Enable a Pilot Connector and choose which School fields may enter GuidelyEdu. Secrets are write-only and never shown after save. Field sharing is opt-in.
            </p>

            <p
                v-if="loadError"
                class="mt-4 text-body text-danger"
                data-testid="connectors-load-error"
                role="alert"
            >
                {{ loadError }}
            </p>

            <div
                v-else-if="!loaded"
                class="mt-6 space-y-3"
                data-testid="connectors-loading"
            >
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <form
                v-else
                class="mt-6 space-y-6"
                data-testid="connectors-form"
                @submit.prevent="save"
            >
                <Card>
                    <h2 class="text-body font-semibold text-text">Connector</h2>
                    <p class="mt-1 text-body text-text-muted">
                        Pilot stub is the supported type until OQ-4. Use Run sync below to upsert a School payload without duplicating Pupils.
                    </p>
                    <label class="mt-4 flex items-center gap-2 text-body text-text">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            data-testid="connector-enabled"
                        >
                        Enable Connector
                    </label>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-secret">Secret</label>
                        <input
                            id="connector-secret"
                            v-model="form.secret"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            :placeholder="hasSecret ? 'Leave blank to keep the current secret' : 'Enter a Connector secret'"
                            data-testid="connector-secret"
                        >
                        <p v-if="hasSecret" class="mt-1 text-body text-text-muted" data-testid="connector-has-secret">
                            A secret is stored. It is never displayed.
                        </p>
                    </div>
                </Card>

                <Card data-testid="field-sharing-grid">
                    <h2 class="text-body font-semibold text-text">School field sharing</h2>
                    <p class="mt-1 text-body text-text-muted">
                        Only checked fields may be copied from the MIS for that School. All fields start off.
                    </p>

                    <section
                        v-for="school in schools"
                        :key="school.id"
                        class="mt-6"
                        :data-testid="`field-share-school-${school.id}`"
                    >
                        <h3 class="text-body font-medium text-text">{{ school.name }}</h3>
                        <fieldset class="mt-2 grid gap-2 sm:grid-cols-2">
                            <legend class="sr-only">Fields for {{ school.name }}</legend>
                            <label
                                v-for="field in shareableFields"
                                :key="`${school.id}-${field.key}`"
                                class="flex items-center gap-2 text-body text-text"
                            >
                                <input
                                    v-model="form.fieldShares[school.id][field.key]"
                                    type="checkbox"
                                    :data-testid="`field-share-${school.id}-${field.key}`"
                                >
                                {{ field.label }}
                            </label>
                        </fieldset>
                    </section>
                </Card>

                <p
                    v-if="saveError"
                    class="text-body text-danger"
                    data-testid="connectors-save-error"
                    role="alert"
                >
                    {{ saveError }}
                </p>
                <p
                    v-if="saveSuccess"
                    class="text-body text-text"
                    data-testid="connectors-save-success"
                >
                    {{ saveSuccess }}
                </p>
                <ButtonPrimary
                    type="submit"
                    :disabled="saving"
                    data-testid="connector-save"
                >
                    {{ saving ? 'Saving…' : 'Save Connector' }}
                </ButtonPrimary>
            </form>

            <Card
                v-if="loaded"
                class="mt-8"
                data-testid="connector-health"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-body font-semibold text-text">Connector health</h2>
                        <p class="mt-1 text-meta text-text-muted" data-testid="connector-health-state">
                            {{ healthState }}
                        </p>
                    </div>
                    <ButtonOutline
                        type="button"
                        :disabled="refreshingHealth"
                        data-testid="connector-health-refresh"
                        @click="refreshHealth"
                    >
                        {{ refreshingHealth ? 'Refreshing…' : 'Refresh health' }}
                    </ButtonOutline>
                </div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Last completed sync</dt>
                        <dd class="mt-1 text-body text-text" data-testid="connector-last-completed">
                            {{ formatHealthDate(health.lastCompletedAt, 'Never completed') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Last failed sync</dt>
                        <dd class="mt-1 text-body text-text" data-testid="connector-last-failed">
                            {{ formatHealthDate(health.lastFailedAt, 'Never failed') }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Last error or warning</dt>
                        <dd class="mt-1 text-body text-text" data-testid="connector-last-error">
                            {{ health.lastError || 'None' }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="healthRefreshError"
                    class="mt-4 text-body text-danger"
                    data-testid="connector-health-refresh-error"
                    role="alert"
                >
                    {{ healthRefreshError }}
                </p>
            </Card>

            <form
                v-if="loaded"
                class="mt-8 space-y-4"
                data-testid="connectors-sync-form"
                @submit.prevent="runSync"
            >
                <Card>
                    <h2 class="text-body font-semibold text-text">Run sync</h2>
                    <p class="mt-1 text-body text-text-muted">
                        Upsert one Pupil by MIS key for a School. Optional Intervention evidence uses an external id so a second sync does not duplicate the record. The Connector must be enabled to run a sync.
                    </p>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-sync-school">School</label>
                        <select
                            id="connector-sync-school"
                            v-model="syncForm.schoolId"
                            required
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="connector-sync-school"
                        >
                            <option disabled value="">Select a School</option>
                            <option
                                v-for="school in schools"
                                :key="school.id"
                                :value="school.id"
                            >
                                {{ school.name }}
                            </option>
                        </select>
                    </div>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-sync-mis-key">MIS key</label>
                        <input
                            id="connector-sync-mis-key"
                            v-model="syncForm.misKey"
                            type="text"
                            required
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="connector-sync-mis-key"
                        >
                    </div>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-sync-external-id">Evidence external id (optional)</label>
                        <input
                            id="connector-sync-external-id"
                            v-model="syncForm.evidenceExternalId"
                            type="text"
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="connector-sync-external-id"
                        >
                    </div>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-sync-provision-code">Evidence provision code (optional)</label>
                        <input
                            id="connector-sync-provision-code"
                            v-model="syncForm.evidenceProvisionCode"
                            type="text"
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="connector-sync-provision-code"
                        >
                    </div>
                    <div class="mt-4">
                        <label class="block text-body text-text" for="connector-sync-occurred-at">Evidence occurred at (optional)</label>
                        <input
                            id="connector-sync-occurred-at"
                            v-model="syncForm.evidenceOccurredAt"
                            type="datetime-local"
                            class="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="connector-sync-occurred-at"
                        >
                    </div>
                </Card>
                <p
                    v-if="syncError"
                    class="text-body text-danger"
                    data-testid="connectors-sync-error"
                    role="alert"
                >
                    {{ syncError }}
                </p>
                <p
                    v-if="syncSuccess"
                    class="text-body text-text"
                    data-testid="connectors-sync-success"
                >
                    {{ syncSuccess }}
                </p>
                <ButtonPrimary
                    type="submit"
                    :disabled="syncing || !form.enabled"
                    data-testid="connector-sync-submit"
                >
                    {{ syncing ? 'Queueing…' : 'Run sync' }}
                </ButtonPrimary>
            </form>
        </template>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import FeatureFlaggedEmpty from '../shared/ui/FeatureFlaggedEmpty.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const shareableFields = [
    { key: 'given_name', label: 'Given name' },
    { key: 'family_name', label: 'Family name' },
    { key: 'mis_key', label: 'MIS key' },
    { key: 'date_of_birth', label: 'Date of birth' },
    { key: 'year_group', label: 'Year group' },
    { key: 'send_status', label: 'SEND status' },
];

const flagUnavailable = ref(false);
const loaded = ref(false);
const loadError = ref('');
const saveError = ref('');
const saveSuccess = ref('');
const saving = ref(false);
const syncError = ref('');
const syncSuccess = ref('');
const syncing = ref(false);
const refreshingHealth = ref(false);
const healthRefreshError = ref('');
const healthState = ref('Never synced');
const hasSecret = ref(false);
const schools = ref([]);
const health = reactive({
    lastStartedAt: null,
    lastCompletedAt: null,
    lastFailedAt: null,
    lastError: '',
});

const form = reactive({
    enabled: false,
    secret: '',
    fieldShares: {},
});

const syncForm = reactive({
    schoolId: '',
    misKey: '',
    evidenceExternalId: '',
    evidenceProvisionCode: '',
    evidenceOccurredAt: '',
});

function emptyFieldMap() {
    return Object.fromEntries(shareableFields.map((field) => [field.key, false]));
}

function firstValidationMessage(payload) {
    const errors = payload?.errors;

    if (errors === null || typeof errors !== 'object') {
        return null;
    }

    for (const messages of Object.values(errors)) {
        if (Array.isArray(messages) && typeof messages[0] === 'string' && messages[0] !== '') {
            return messages[0];
        }

        if (typeof messages === 'string' && messages !== '') {
            return messages;
        }
    }

    return null;
}

function applyConnector(payload) {
    form.enabled = payload?.enabled === true;
    form.secret = '';
    hasSecret.value = payload?.has_secret === true;
    applyHealth(payload);

    const shares = payload?.field_shares ?? [];
    const next = {};

    for (const school of schools.value) {
        next[school.id] = emptyFieldMap();
    }

    for (const share of shares) {
        if (!share?.school_id) {
            continue;
        }

        next[share.school_id] = {
            ...emptyFieldMap(),
            ...(share.fields ?? {}),
        };
    }

    form.fieldShares = next;
}

function applyHealth(payload) {
    health.lastStartedAt = payload?.last_sync_started_at ?? null;
    health.lastCompletedAt = payload?.last_sync_completed_at ?? null;
    health.lastFailedAt = payload?.last_sync_failed_at ?? null;
    health.lastError = payload?.last_error ?? '';

    const started = Date.parse(health.lastStartedAt ?? '');
    const completed = Date.parse(health.lastCompletedAt ?? '');
    const failed = Date.parse(health.lastFailedAt ?? '');
    const startedTime = Number.isNaN(started) ? 0 : started;
    const completedTime = Number.isNaN(completed) ? 0 : completed;
    const failedTime = Number.isNaN(failed) ? 0 : failed;
    const latestFinished = Math.max(completedTime, failedTime);

    if (startedTime > latestFinished) {
        healthState.value = 'Sync in progress';
    } else if (latestFinished === 0) {
        healthState.value = 'Never synced';
    } else if (completedTime >= failedTime) {
        healthState.value = health.lastError ? 'Last sync completed with warnings' : 'Last sync completed';
    } else {
        healthState.value = 'Last sync failed';
    }
}

function formatHealthDate(value, fallback) {
    if (!value) {
        return fallback;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return fallback;
    }

    return parsed.toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/London',
    });
}

onMounted(async () => {
    try {
        const [connectorResponse, schoolsResponse] = await Promise.all([
            apiFetch('/api/v1/connectors'),
            apiFetch('/api/v1/schools'),
        ]);

        if (connectorResponse.status === 403) {
            const payload = await connectorResponse.clone().json().catch(() => ({}));

            if (payload.code === 'feature_not_available') {
                flagUnavailable.value = true;
                return;
            }

            loadError.value = 'You don’t have access.';
            return;
        }

        if (!connectorResponse.ok) {
            loadError.value = 'Unable to load Connector settings.';
            return;
        }

        if (!schoolsResponse.ok) {
            loadError.value = 'Unable to load Schools.';
            return;
        }

        const connectorPayload = await connectorResponse.json();
        const schoolsPayload = await schoolsResponse.json();
        schools.value = schoolsPayload.data ?? [];
        applyConnector(connectorPayload.data);
        syncForm.schoolId = schools.value[0]?.id ?? '';
        loaded.value = true;
    } catch {
        loadError.value = 'Unable to load Connector settings.';
    }
});

async function save() {
    saving.value = true;
    saveError.value = '';
    saveSuccess.value = '';

    const body = {
        enabled: form.enabled,
        type: 'pilot_stub',
        field_shares: schools.value.map((school) => ({
            school_id: school.id,
            fields: { ...emptyFieldMap(), ...(form.fieldShares[school.id] ?? {}) },
        })),
    };

    if (form.secret !== '') {
        body.secret = form.secret;
    }

    try {
        const response = await apiFetch('/api/v1/connectors', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 403 && payload.code === 'feature_not_available') {
            flagUnavailable.value = true;
            return;
        }

        if (!response.ok) {
            saveError.value = firstValidationMessage(payload)
                ?? payload.message
                ?? 'Unable to save Connector settings.';
            return;
        }

        applyConnector(payload.data);
        saveSuccess.value = 'Connector settings saved.';
    } catch {
        saveError.value = 'Unable to save Connector settings.';
    } finally {
        saving.value = false;
    }
}

async function refreshHealth() {
    refreshingHealth.value = true;
    healthRefreshError.value = '';

    try {
        const response = await apiFetch('/api/v1/connectors');

        if (!response.ok) {
            healthRefreshError.value = 'Unable to refresh Connector health.';
            return;
        }

        const payload = await response.json();
        applyHealth(payload.data);
    } catch {
        healthRefreshError.value = 'Unable to refresh Connector health.';
    } finally {
        refreshingHealth.value = false;
    }
}

async function runSync() {
    syncing.value = true;
    syncError.value = '';
    syncSuccess.value = '';

    try {
        const pupil = { mis_key: syncForm.misKey };

        if (syncForm.evidenceExternalId !== '') {
            pupil.evidence_external_id = syncForm.evidenceExternalId;
        }

        if (syncForm.evidenceProvisionCode !== '') {
            pupil.evidence_provision_code = syncForm.evidenceProvisionCode;
        }

        if (syncForm.evidenceOccurredAt !== '') {
            const occurredAt = new Date(syncForm.evidenceOccurredAt);

            if (Number.isNaN(occurredAt.getTime())) {
                syncError.value = 'Evidence occurred at must be a valid date.';

                return;
            }

            pupil.evidence_occurred_at = occurredAt.toISOString();
        }

        const response = await apiFetch('/api/v1/connectors/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: syncForm.schoolId,
                pupils: [pupil],
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 403 && payload.code === 'feature_not_available') {
            flagUnavailable.value = true;
            return;
        }

        if (!response.ok) {
            syncError.value = firstValidationMessage(payload)
                ?? payload.message
                ?? 'Unable to run Connector sync.';
            return;
        }

        syncSuccess.value = payload.message ?? 'Connector sync queued.';
    } catch {
        syncError.value = 'Unable to run Connector sync.';
    } finally {
        syncing.value = false;
    }
}
</script>
