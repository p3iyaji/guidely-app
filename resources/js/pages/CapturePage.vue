<template>
    <div data-testid="capture-page">
        <div>
            <h1 class="text-heading font-semibold text-text">{{ pageTitle }}</h1>
            <p class="mt-1 text-body text-text-muted">
                {{ pageDescription }}
            </p>
        </div>

        <div
            class="mt-4 flex flex-wrap gap-2"
            role="tablist"
            aria-label="Capture mode"
            data-testid="capture-mode-switch"
        >
            <button
                type="button"
                role="tab"
                class="min-h-11 rounded-md border px-4 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                :class="mode === 'observation'
                    ? 'border-border bg-surface-muted text-text font-semibold'
                    : 'border-border bg-surface text-text-muted'"
                :aria-selected="mode === 'observation' ? 'true' : 'false'"
                :disabled="submitting"
                data-testid="capture-mode-observation"
                @click="setMode('observation')"
            >
                Observation
            </button>
            <button
                type="button"
                role="tab"
                class="min-h-11 rounded-md border px-4 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                :class="mode === 'intervention'
                    ? 'border-border bg-surface-muted text-text font-semibold'
                    : 'border-border bg-surface text-text-muted'"
                :aria-selected="mode === 'intervention' ? 'true' : 'false'"
                :disabled="submitting"
                data-testid="capture-mode-intervention"
                @click="setMode('intervention')"
            >
                Intervention
            </button>
            <button
                type="button"
                role="tab"
                class="min-h-11 rounded-md border px-4 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                :class="mode === 'response'
                    ? 'border-border bg-surface-muted text-text font-semibold'
                    : 'border-border bg-surface text-text-muted'"
                :aria-selected="mode === 'response' ? 'true' : 'false'"
                :disabled="submitting"
                data-testid="capture-mode-response"
                @click="setMode('response')"
            >
                Pupil Response
            </button>
        </div>

        <Card
            v-if="confirmation"
            class="mt-6"
            data-testid="capture-confirmation"
        >
            <h2 class="text-body font-semibold text-text">{{ confirmationTitle }}</h2>
            <p class="mt-2 text-body text-text">
                {{ confirmationBlurb }}
            </p>
            <p class="mt-2 text-body text-text-muted" data-testid="capture-confirmation-id">
                Reference: {{ confirmation.id }}
            </p>
            <p
                v-if="confirmation.setting?.label"
                class="mt-1 text-meta text-text-muted"
                data-testid="capture-confirmation-setting"
            >
                Setting: {{ confirmation.setting.label }}
            </p>
            <p
                v-if="confirmation.provision?.label"
                class="mt-1 text-meta text-text-muted"
                data-testid="capture-confirmation-provision"
            >
                Provision: {{ confirmation.provision.label }}
            </p>
            <p
                v-if="confirmation.related_intervention"
                class="mt-1 text-meta text-text-muted"
                data-testid="capture-confirmation-related-intervention"
            >
                Linked Intervention: {{ interventionLabel(confirmation.related_intervention) }}
            </p>
            <div class="mt-4">
                <ButtonPrimary
                    class="min-h-11 w-full sm:w-auto"
                    data-testid="capture-another"
                    @click="resetForm"
                >
                    {{ captureAnotherLabel }}
                </ButtonPrimary>
            </div>
        </Card>

        <template v-else>
            <div v-if="loading" class="mt-6 space-y-3" data-testid="capture-loading">
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <Card
                v-else-if="loadError"
                class="mt-6"
                data-testid="capture-error"
            >
                <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            </Card>

            <Card
                v-else
                class="mt-6"
                data-testid="capture-form-card"
            >
                <form class="space-y-4" @submit.prevent="submitCapture">
                    <div>
                        <label class="block text-body text-text" for="capture-pupil">Pupil</label>
                        <select
                            id="capture-pupil"
                            v-model="form.pupil_id"
                            required
                            class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-pupil"
                            :aria-describedby="fieldErrors.pupil_id ? 'capture-pupil-error' : undefined"
                            :aria-invalid="fieldErrors.pupil_id ? 'true' : undefined"
                        >
                            <option disabled value="">Select a Pupil</option>
                            <option
                                v-for="pupil in pupils"
                                :key="pupil.id"
                                :value="pupil.id"
                            >
                                {{ displayName(pupil) }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.pupil_id"
                            id="capture-pupil-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-pupil-error"
                            role="alert"
                        >
                            {{ fieldErrors.pupil_id }}
                        </p>
                        <p
                            v-if="pupils.length === 0"
                            class="mt-2 text-body text-text-muted"
                            data-testid="capture-pupils-empty"
                        >
                            No Pupils in your list to capture against.
                        </p>
                    </div>

                    <div>
                        <label class="block text-body text-text" for="capture-occurred-at">Session date and time</label>
                        <input
                            id="capture-occurred-at"
                            v-model="form.occurred_at_local"
                            type="datetime-local"
                            required
                            class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-occurred-at"
                            :aria-describedby="fieldErrors.occurred_at ? 'capture-occurred-at-error' : undefined"
                            :aria-invalid="fieldErrors.occurred_at ? 'true' : undefined"
                        >
                        <p
                            v-if="fieldErrors.occurred_at"
                            id="capture-occurred-at-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-occurred-at-error"
                            role="alert"
                        >
                            {{ fieldErrors.occurred_at }}
                        </p>
                    </div>

                    <div v-if="mode === 'observation'">
                        <label class="block text-body text-text" for="capture-setting">Setting</label>
                        <select
                            id="capture-setting"
                            v-model="form.setting_term_id"
                            required
                            class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-setting"
                            :aria-describedby="settingDescribedBy"
                            :aria-invalid="fieldErrors.setting_term_id || settingsEmptyError ? 'true' : undefined"
                        >
                            <option disabled value="">Select a Setting</option>
                            <option
                                v-for="term in settingTerms"
                                :key="term.id"
                                :value="term.id"
                            >
                                {{ term.label }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.setting_term_id"
                            id="capture-setting-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-setting-error"
                            role="alert"
                        >
                            {{ fieldErrors.setting_term_id }}
                        </p>
                        <p
                            v-else-if="settingsEmptyError"
                            id="capture-settings-empty"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-settings-empty"
                            role="alert"
                        >
                            {{ settingsEmptyError }}
                        </p>
                    </div>

                    <div v-else-if="mode === 'intervention'">
                        <label class="block text-body text-text" for="capture-provision">Provision</label>
                        <select
                            id="capture-provision"
                            v-model="form.provision_term_id"
                            required
                            class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-provision"
                            :aria-describedby="provisionDescribedBy"
                            :aria-invalid="fieldErrors.provision_term_id || provisionsEmptyError ? 'true' : undefined"
                        >
                            <option disabled value="">Select a Provision</option>
                            <option
                                v-for="term in provisionTerms"
                                :key="term.id"
                                :value="term.id"
                            >
                                {{ term.label }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.provision_term_id"
                            id="capture-provision-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-provision-error"
                            role="alert"
                        >
                            {{ fieldErrors.provision_term_id }}
                        </p>
                        <p
                            v-else-if="provisionsEmptyError"
                            id="capture-provisions-empty"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-provisions-empty"
                            role="alert"
                        >
                            {{ provisionsEmptyError }}
                        </p>
                    </div>

                    <div v-else>
                        <label class="block text-body text-text" for="capture-related-intervention">
                            Related Intervention (optional)
                        </label>
                        <select
                            id="capture-related-intervention"
                            v-model="form.related_intervention_id"
                            class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-related-intervention"
                            :disabled="!form.pupil_id || interventionsLoading"
                            :aria-busy="interventionsLoading ? 'true' : 'false'"
                            :aria-describedby="relatedInterventionDescribedBy"
                            :aria-invalid="fieldErrors.related_intervention_id ? 'true' : undefined"
                        >
                            <option value="">No linked Intervention</option>
                            <option
                                v-for="item in interventions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ interventionLabel(item) }}
                            </option>
                        </select>
                        <p
                            v-if="fieldErrors.related_intervention_id"
                            id="capture-related-intervention-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-related-intervention-error"
                            role="alert"
                        >
                            {{ fieldErrors.related_intervention_id }}
                        </p>
                        <p
                            v-else-if="interventionsLoading"
                            id="capture-interventions-loading"
                            class="mt-1 text-body text-text-muted"
                            data-testid="capture-interventions-loading"
                        >
                            Loading Interventions…
                        </p>
                        <p
                            v-else-if="interventionsEmptyMessage"
                            id="capture-interventions-empty"
                            class="mt-1 text-body text-text-muted"
                            data-testid="capture-interventions-empty"
                        >
                            {{ interventionsEmptyMessage }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-body text-text" for="capture-body">{{ bodyLabel }}</label>
                        <textarea
                            id="capture-body"
                            v-model="form.body"
                            :required="mode !== 'intervention'"
                            rows="4"
                            maxlength="5000"
                            class="mt-1 w-full max-w-2xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            data-testid="capture-body"
                            :aria-describedby="fieldErrors.body ? 'capture-body-error' : undefined"
                            :aria-invalid="fieldErrors.body ? 'true' : undefined"
                        />
                        <p
                            v-if="fieldErrors.body"
                            id="capture-body-error"
                            class="mt-1 text-body text-danger"
                            data-testid="capture-body-error"
                            role="alert"
                        >
                            {{ fieldErrors.body }}
                        </p>
                    </div>

                    <p
                        v-if="submitError"
                        class="text-body text-danger"
                        data-testid="capture-submit-error"
                        role="alert"
                    >
                        {{ submitError }}
                    </p>

                    <ButtonPrimary
                        type="submit"
                        class="min-h-11 w-full sm:w-auto"
                        :disabled="submitDisabled"
                        data-testid="capture-submit"
                    >
                        {{ submitting ? 'Submitting…' : submitLabel }}
                    </ButtonPrimary>
                </form>
            </Card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiFetch } from '../api/client';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const route = useRoute();

const mode = ref('observation');
const pupils = ref([]);
const settingTerms = ref([]);
const provisionTerms = ref([]);
const interventions = ref([]);
const interventionsLoading = ref(false);
const interventionsEmptyMessage = ref('');
/** Bumped to ignore stale Intervention list responses when pupil/mode changes. */
let interventionsLoadToken = 0;
const loading = ref(true);
const loadError = ref('');
const settingsEmptyError = ref('');
const provisionsEmptyError = ref('');
const submitting = ref(false);
const submitError = ref('');
const confirmation = ref(null);

const form = reactive({
    pupil_id: '',
    occurred_at_local: defaultLocalDateTime(),
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});

const fieldErrors = reactive({
    pupil_id: '',
    occurred_at: '',
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});

const pageTitle = computed(() => {
    if (mode.value === 'intervention') {
        return 'Capture Intervention';
    }

    if (mode.value === 'response') {
        return 'Capture Pupil Response';
    }

    return 'Capture Observation';
});

const pageDescription = computed(() => {
    if (mode.value === 'intervention') {
        return 'Record provision delivered for a Pupil. Required fields use Ontology terms for Provision.';
    }

    if (mode.value === 'response') {
        return 'Record how a Pupil responded. Link an Intervention when available, or use the session date and time as context.';
    }

    return 'Record what was observed for a Pupil. Required fields use Ontology terms for Setting.';
});

const bodyLabel = computed(() => {
    if (mode.value === 'intervention') {
        return 'Notes (optional)';
    }

    if (mode.value === 'response') {
        return 'How the Pupil responded';
    }

    return 'What was observed';
});

const submitLabel = computed(() => {
    if (mode.value === 'intervention') {
        return 'Submit Intervention';
    }

    if (mode.value === 'response') {
        return 'Submit Pupil Response';
    }

    return 'Submit Observation';
});

const confirmationType = computed(() => confirmation.value?.type ?? '');

const confirmationTitle = computed(() => {
    if (confirmationType.value === 'intervention') {
        return 'Intervention submitted';
    }

    if (confirmationType.value === 'response') {
        return 'Pupil Response submitted';
    }

    return 'Observation submitted';
});

const confirmationBlurb = computed(() => {
    if (confirmationType.value === 'intervention') {
        return 'The Intervention is on the Evidence Base path as a submitted record.';
    }

    if (confirmationType.value === 'response') {
        return 'The Pupil Response is on the Evidence Base path as a submitted record.';
    }

    return 'The Observation is on the Evidence Base path as a submitted record.';
});

const captureAnotherLabel = computed(() => {
    if (confirmationType.value === 'intervention') {
        return 'Capture another Intervention';
    }

    if (confirmationType.value === 'response') {
        return 'Capture another Pupil Response';
    }

    return 'Capture another Observation';
});

const submitDisabled = computed(() => {
    if (submitting.value || pupils.value.length === 0) {
        return true;
    }

    if (mode.value === 'observation') {
        return settingTerms.value.length === 0;
    }

    if (mode.value === 'intervention') {
        return provisionTerms.value.length === 0;
    }

    return false;
});

const settingDescribedBy = computed(() => {
    if (fieldErrors.setting_term_id) {
        return 'capture-setting-error';
    }

    if (settingsEmptyError.value) {
        return 'capture-settings-empty';
    }

    return undefined;
});

const provisionDescribedBy = computed(() => {
    if (fieldErrors.provision_term_id) {
        return 'capture-provision-error';
    }

    if (provisionsEmptyError.value) {
        return 'capture-provisions-empty';
    }

    return undefined;
});

const relatedInterventionDescribedBy = computed(() => {
    if (fieldErrors.related_intervention_id) {
        return 'capture-related-intervention-error';
    }

    if (interventionsLoading.value) {
        return 'capture-interventions-loading';
    }

    if (interventionsEmptyMessage.value) {
        return 'capture-interventions-empty';
    }

    return undefined;
});

watch(pageTitle, (title) => {
    document.title = title;
    route.meta.title = title;
}, { immediate: true });

watch(() => form.pupil_id, async (pupilId) => {
    form.related_intervention_id = '';
    fieldErrors.related_intervention_id = '';

    if (mode.value !== 'response') {
        invalidateInterventionsLoad();
        interventions.value = [];
        interventionsEmptyMessage.value = '';

        return;
    }

    await loadInterventionsForPupil(pupilId);
});

onMounted(async () => {
    await loadFormData();
});

/**
 * @param {'observation'|'intervention'|'response'} nextMode
 */
function setMode(nextMode) {
    if (submitting.value || mode.value === nextMode) {
        return;
    }

    mode.value = nextMode;
    confirmation.value = null;
    submitError.value = '';
    clearFieldErrors();
    form.setting_term_id = '';
    form.provision_term_id = '';
    form.related_intervention_id = '';
    form.body = '';

    if (nextMode === 'response' && form.pupil_id) {
        loadInterventionsForPupil(form.pupil_id);
    } else if (nextMode !== 'response') {
        invalidateInterventionsLoad();
        interventions.value = [];
        interventionsEmptyMessage.value = '';
    }
}

/**
 * @param {{ given_name?: string, family_name?: string }} pupil
 */
function displayName(pupil) {
    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
}

/**
 * @param {{ occurred_at?: string, provision?: { label?: string }, id?: string }} item
 */
function interventionLabel(item) {
    const when = formatOccurredAt(item.occurred_at);
    const provision = item.provision?.label;

    if (when && provision) {
        return `${when} — ${provision}`;
    }

    return when || provision || item.id || 'Intervention';
}

/**
 * @param {string|undefined} iso
 */
function formatOccurredAt(iso) {
    if (!iso) {
        return '';
    }

    const parsed = new Date(iso);

    if (Number.isNaN(parsed.getTime())) {
        return iso;
    }

    return parsed.toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/London',
    });
}

function defaultLocalDateTime() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

/**
 * @param {string} localValue
 */
function toUtcIso(localValue) {
    const parsed = new Date(localValue);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toISOString();
}

/**
 * @param {unknown} value
 * @returns {array}
 */
function asArray(value) {
    return Array.isArray(value) ? value : [];
}

function clearFieldErrors() {
    fieldErrors.pupil_id = '';
    fieldErrors.occurred_at = '';
    fieldErrors.setting_term_id = '';
    fieldErrors.provision_term_id = '';
    fieldErrors.related_intervention_id = '';
    fieldErrors.body = '';
}

function resetForm() {
    confirmation.value = null;
    submitError.value = '';
    clearFieldErrors();
    form.pupil_id = '';
    form.occurred_at_local = defaultLocalDateTime();
    form.setting_term_id = '';
    form.provision_term_id = '';
    form.related_intervention_id = '';
    form.body = '';
    invalidateInterventionsLoad();
    interventions.value = [];
    interventionsEmptyMessage.value = '';
}

function invalidateInterventionsLoad() {
    interventionsLoadToken += 1;
    interventionsLoading.value = false;
}

async function loadFormData() {
    loading.value = true;
    loadError.value = '';
    settingsEmptyError.value = '';
    provisionsEmptyError.value = '';

    try {
        const [pupilsResponse, settingsResponse, provisionsResponse] = await Promise.all([
            apiFetch('/api/v1/pupils'),
            apiFetch('/api/v1/ontology/setting-terms'),
            apiFetch('/api/v1/ontology/provision-terms'),
        ]);

        if (!pupilsResponse.ok) {
            loadError.value = 'Unable to load Capture form data.';

            return;
        }

        const pupilsPayload = await pupilsResponse.json();
        pupils.value = asArray(pupilsPayload.data);

        if (settingsResponse.ok) {
            const settingsPayload = await settingsResponse.json();
            settingTerms.value = asArray(settingsPayload.data);

            if (settingTerms.value.length === 0) {
                settingsEmptyError.value = 'No Setting terms are available for Capture.';
            }
        } else {
            settingTerms.value = [];
            settingsEmptyError.value = 'Unable to load Setting terms for Capture.';
        }

        if (provisionsResponse.ok) {
            const provisionsPayload = await provisionsResponse.json();
            provisionTerms.value = asArray(provisionsPayload.data);

            if (provisionTerms.value.length === 0) {
                provisionsEmptyError.value = 'No Provision terms are available for Capture.';
            }
        } else {
            provisionTerms.value = [];
            provisionsEmptyError.value = 'Unable to load Provision terms for Capture.';
        }
    } catch {
        loadError.value = 'Unable to load Capture form data.';
    } finally {
        loading.value = false;
    }
}

/**
 * @param {string} pupilId
 */
async function loadInterventionsForPupil(pupilId) {
    const token = ++interventionsLoadToken;
    interventions.value = [];
    interventionsEmptyMessage.value = '';

    if (!pupilId) {
        interventionsLoading.value = false;

        return;
    }

    interventionsLoading.value = true;

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/interventions`);

        if (token !== interventionsLoadToken) {
            return;
        }

        if (!response.ok) {
            interventionsEmptyMessage.value = 'Unable to load Interventions for this Pupil.';

            return;
        }

        const payload = await response.json();

        if (token !== interventionsLoadToken) {
            return;
        }

        interventions.value = asArray(payload.data);

        if (interventions.value.length === 0) {
            interventionsEmptyMessage.value = 'No Interventions recorded for this Pupil yet. You can still submit with the session date and time.';
        }
    } catch {
        if (token !== interventionsLoadToken) {
            return;
        }

        interventionsEmptyMessage.value = 'Unable to load Interventions for this Pupil.';
    } finally {
        if (token === interventionsLoadToken) {
            interventionsLoading.value = false;
        }
    }
}

async function submitCapture() {
    if (mode.value === 'intervention') {
        await submitIntervention();

        return;
    }

    if (mode.value === 'response') {
        await submitResponse();

        return;
    }

    await submitObservation();
}

async function submitObservation() {
    if (submitting.value || submitDisabled.value) {
        return;
    }

    clearFieldErrors();
    submitError.value = '';

    const occurredAt = toUtcIso(form.occurred_at_local);

    if (!occurredAt) {
        fieldErrors.occurred_at = 'Enter a valid session date and time.';

        return;
    }

    submitting.value = true;

    try {
        const response = await apiFetch('/api/v1/observations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Client-Type': 'web',
            },
            body: JSON.stringify({
                pupil_id: form.pupil_id,
                occurred_at: occurredAt,
                setting_term_id: form.setting_term_id,
                body: form.body,
                client_type: 'web',
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 422) {
            const errors = payload.errors ?? {};
            fieldErrors.pupil_id = errors.pupil_id?.[0] ?? '';
            fieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            fieldErrors.setting_term_id = errors.setting_term_id?.[0] ?? errors.setting?.[0] ?? '';
            fieldErrors.body = errors.body?.[0] ?? '';
            submitError.value = payload.message ?? 'Please correct the highlighted fields.';

            return;
        }

        if (!response.ok) {
            submitError.value = payload.message ?? 'Unable to submit Observation.';

            return;
        }

        if (!payload.data?.id) {
            submitError.value = 'Observation was accepted but no record reference was returned.';

            return;
        }

        confirmation.value = payload.data;
    } catch {
        submitError.value = 'Unable to submit Observation.';
    } finally {
        submitting.value = false;
    }
}

async function submitIntervention() {
    if (submitting.value || submitDisabled.value) {
        return;
    }

    clearFieldErrors();
    submitError.value = '';

    const occurredAt = toUtcIso(form.occurred_at_local);

    if (!occurredAt) {
        fieldErrors.occurred_at = 'Enter a valid session date and time.';

        return;
    }

    submitting.value = true;

    try {
        const response = await apiFetch('/api/v1/interventions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Client-Type': 'web',
            },
            body: JSON.stringify({
                pupil_id: form.pupil_id,
                occurred_at: occurredAt,
                provision_term_id: form.provision_term_id,
                body: form.body || null,
                client_type: 'web',
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 422) {
            const errors = payload.errors ?? {};
            fieldErrors.pupil_id = errors.pupil_id?.[0] ?? '';
            fieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            fieldErrors.provision_term_id = errors.provision_term_id?.[0] ?? errors.provision?.[0] ?? '';
            fieldErrors.body = errors.body?.[0] ?? '';
            submitError.value = payload.message ?? 'Please correct the highlighted fields.';

            return;
        }

        if (!response.ok) {
            submitError.value = payload.message ?? 'Unable to submit Intervention.';

            return;
        }

        if (!payload.data?.id) {
            submitError.value = 'Intervention was accepted but no record reference was returned.';

            return;
        }

        confirmation.value = payload.data;
    } catch {
        submitError.value = 'Unable to submit Intervention.';
    } finally {
        submitting.value = false;
    }
}

async function submitResponse() {
    if (submitting.value || submitDisabled.value) {
        return;
    }

    clearFieldErrors();
    submitError.value = '';

    const occurredAt = toUtcIso(form.occurred_at_local);

    if (!occurredAt) {
        fieldErrors.occurred_at = 'Enter a valid session date and time.';

        return;
    }

    submitting.value = true;

    try {
        const response = await apiFetch('/api/v1/responses', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Client-Type': 'web',
            },
            body: JSON.stringify({
                pupil_id: form.pupil_id,
                occurred_at: occurredAt,
                related_intervention_id: form.related_intervention_id || null,
                body: form.body,
                client_type: 'web',
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 422) {
            const errors = payload.errors ?? {};
            fieldErrors.pupil_id = errors.pupil_id?.[0] ?? '';
            fieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            fieldErrors.related_intervention_id = errors.related_intervention_id?.[0] ?? '';
            fieldErrors.body = errors.body?.[0] ?? '';
            submitError.value = payload.message ?? 'Please correct the highlighted fields.';

            return;
        }

        if (!response.ok) {
            submitError.value = payload.message ?? 'Unable to submit Pupil Response.';

            return;
        }

        if (!payload.data?.id) {
            submitError.value = 'Pupil Response was accepted but no record reference was returned.';

            return;
        }

        confirmation.value = payload.data;
    } catch {
        submitError.value = 'Unable to submit Pupil Response.';
    } finally {
        submitting.value = false;
    }
}
</script>
