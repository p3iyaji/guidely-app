<template>
    <div data-testid="capture-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Capture Observation</h1>
            <p class="mt-1 text-body text-text-muted">
                Record what was observed for a Pupil. Required fields use Ontology terms for Setting.
            </p>
        </div>

        <Card
            v-if="confirmation"
            class="mt-6"
            data-testid="capture-confirmation"
        >
            <h2 class="text-body font-semibold text-text">Observation submitted</h2>
            <p class="mt-2 text-body text-text">
                The Observation is on the Evidence Base path as a submitted record.
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
            <div class="mt-4">
                <ButtonPrimary
                    class="min-h-11 w-full sm:w-auto"
                    data-testid="capture-another"
                    @click="resetForm"
                >
                    Capture another Observation
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
                <form class="space-y-4" @submit.prevent="submitObservation">
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

                    <div>
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

                    <div>
                        <label class="block text-body text-text" for="capture-body">What was observed</label>
                        <textarea
                            id="capture-body"
                            v-model="form.body"
                            required
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
                        {{ submitting ? 'Submitting…' : 'Submit Observation' }}
                    </ButtonPrimary>
                </form>
            </Card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { apiFetch } from '../api/client';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const route = useRoute();

const pupils = ref([]);
const settingTerms = ref([]);
const loading = ref(true);
const loadError = ref('');
const settingsEmptyError = ref('');
const submitting = ref(false);
const submitError = ref('');
const confirmation = ref(null);

const form = reactive({
    pupil_id: '',
    occurred_at_local: defaultLocalDateTime(),
    setting_term_id: '',
    body: '',
});

const fieldErrors = reactive({
    pupil_id: '',
    occurred_at: '',
    setting_term_id: '',
    body: '',
});

const submitDisabled = computed(
    () => submitting.value || pupils.value.length === 0 || settingTerms.value.length === 0,
);

const settingDescribedBy = computed(() => {
    if (fieldErrors.setting_term_id) {
        return 'capture-setting-error';
    }

    if (settingsEmptyError.value) {
        return 'capture-settings-empty';
    }

    return undefined;
});

onMounted(async () => {
    document.title = 'Capture Observation';
    route.meta.title = 'Capture Observation';
    await loadFormData();
});

/**
 * @param {{ given_name?: string, family_name?: string }} pupil
 */
function displayName(pupil) {
    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
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
    fieldErrors.body = '';
}

function resetForm() {
    confirmation.value = null;
    submitError.value = '';
    clearFieldErrors();
    form.pupil_id = '';
    form.occurred_at_local = defaultLocalDateTime();
    form.setting_term_id = '';
    form.body = '';
}

async function loadFormData() {
    loading.value = true;
    loadError.value = '';
    settingsEmptyError.value = '';

    try {
        const [pupilsResponse, settingsResponse] = await Promise.all([
            apiFetch('/api/v1/pupils'),
            apiFetch('/api/v1/ontology/setting-terms'),
        ]);

        if (!pupilsResponse.ok) {
            loadError.value = 'Unable to load Capture form data.';

            return;
        }

        if (!settingsResponse.ok) {
            loadError.value = 'Unable to load Setting terms for Capture.';

            return;
        }

        const pupilsPayload = await pupilsResponse.json();
        const settingsPayload = await settingsResponse.json();

        pupils.value = asArray(pupilsPayload.data);
        settingTerms.value = asArray(settingsPayload.data);

        if (settingTerms.value.length === 0) {
            settingsEmptyError.value = 'No Setting terms are available for Capture.';
        }
    } catch {
        loadError.value = 'Unable to load Capture form data.';
    } finally {
        loading.value = false;
    }
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
</script>
