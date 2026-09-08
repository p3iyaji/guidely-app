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
                        Type: Pilot stub. Live MIS sync is not part of this screen.
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
        </template>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
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
const hasSecret = ref(false);
const schools = ref([]);

const form = reactive({
    enabled: false,
    secret: '',
    fieldShares: {},
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
</script>
