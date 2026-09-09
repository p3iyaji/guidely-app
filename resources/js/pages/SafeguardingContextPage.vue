<template>
    <div data-testid="safeguarding-context-page">
        <FeatureFlaggedEmpty v-if="flagUnavailable" />

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text">Safeguarding context</h1>
                <p class="mt-1 text-body text-text-muted">
                    Presence and severity category only. This is context for documentation,
                    not a safeguarding or child-protection case system.
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="safeguarding-context-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <p
                v-if="saveSuccess"
                class="mt-4 text-body text-text"
                data-testid="safeguarding-context-save-success"
            >
                {{ saveSuccess }}
            </p>

            <div v-if="loading" class="mt-6 space-y-3" data-testid="safeguarding-context-loading">
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>

            <Card
                v-else-if="loadError"
                class="mt-6"
                data-testid="safeguarding-context-error"
            >
                <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            </Card>

            <Card
                v-else-if="signals.length === 0"
                class="mt-6"
                data-testid="safeguarding-context-empty"
            >
                <p class="text-body text-text-muted">No safeguarding context signals.</p>
            </Card>

            <ul
                v-else
                class="mt-6 space-y-3"
                data-testid="safeguarding-context-list"
            >
                <li
                    v-for="signal in signals"
                    :key="signal.id"
                    class="rounded-md border border-border bg-surface px-4 py-3"
                    data-testid="safeguarding-context-item"
                >
                    <p class="text-body font-medium text-text">
                        {{ signal.given_name }} {{ signal.family_name }}
                    </p>
                    <p class="mt-1 text-body text-text-muted" data-testid="safeguarding-context-category">
                        {{ categoryLabel(signal) }}
                    </p>

                    <form
                        class="mt-3 border-t border-border pt-3"
                        :data-testid="`record-form-${signal.pupil_id}`"
                        @submit.prevent="saveSignal(signal)"
                    >
                        <label class="flex items-center gap-2 text-body text-text">
                            <input
                                v-model="forms[signal.id].present"
                                type="checkbox"
                                :data-testid="`record-present-${signal.pupil_id}`"
                            >
                            Present
                        </label>

                        <div class="mt-3 max-w-xs">
                            <label class="block text-body text-text" :for="`record-severity-${signal.pupil_id}`">
                                Severity category
                            </label>
                            <select
                                :id="`record-severity-${signal.pupil_id}`"
                                v-model="forms[signal.id].severity"
                                class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                                :disabled="!forms[signal.id].present || forms[signal.id].saving"
                                data-testid="record-severity-select"
                            >
                                <option value="" disabled>Select a severity</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <p
                            v-if="forms[signal.id].error"
                            class="mt-2 text-body text-danger"
                            :data-testid="`record-error-${signal.pupil_id}`"
                            role="alert"
                        >
                            {{ forms[signal.id].error }}
                        </p>

                        <ButtonPrimary
                            v-if="isDirty(signal)"
                            type="submit"
                            class="mt-3"
                            :disabled="forms[signal.id].saving"
                            :data-testid="`record-save-${signal.pupil_id}`"
                        >
                            {{ forms[signal.id].saving ? 'Saving…' : 'Save context' }}
                        </ButtonPrimary>
                    </form>
                </li>
            </ul>
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

const loading = ref(true);
const loadError = ref('');
const flagUnavailable = ref(false);
const signals = ref([]);
const saveSuccess = ref('');
const forms = reactive({});
let loadSeq = 0;

onMounted(async () => {
    await loadSignals();
});

async function loadSignals() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    flagUnavailable.value = false;

    try {
        const response = await apiFetch('/api/v1/safeguarding-signals');

        if (seq !== loadSeq) {
            return;
        }

        if (response.status === 403) {
            const payload = await response.clone().json().catch(() => ({}));

            if (isRecord(payload) && payload.code === 'feature_not_available') {
                flagUnavailable.value = true;

                return;
            }

            loadError.value = 'You don’t have access.';

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load safeguarding context.';

            return;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return;
        }

        signals.value = Array.isArray(payload.data)
            ? payload.data.filter(isRecord).map(normaliseSignal).filter((row) => row.id !== '')
            : [];

        syncForms();
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        loadError.value = 'Unable to load safeguarding context.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {Record<string, unknown>} row
 */
function normaliseSignal(row) {
    return {
        id: typeof row.id === 'string' ? row.id.trim() : '',
        pupil_id: typeof row.pupil_id === 'string' ? row.pupil_id : '',
        given_name: typeof row.given_name === 'string' ? row.given_name : '',
        family_name: typeof row.family_name === 'string' ? row.family_name : '',
        present: Boolean(row.present),
        severity: typeof row.severity === 'string' ? row.severity : '',
    };
}

/**
 * @param {{ present: boolean, severity: string }} signal
 */
function categoryLabel(signal) {
    if (! signal.present) {
        return 'Not present';
    }

    const labels = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
    };

    return `Present · ${labels[signal.severity] ?? signal.severity}`;
}

/**
 * @param {{ id: string, present: boolean, severity: string }} signal
 */
function formState(signal) {
    if (!forms[signal.id]) {
        forms[signal.id] = {
            present: signal.present,
            severity: signal.severity || '',
            saving: false,
            error: '',
        };
    }

    return forms[signal.id];
}

function syncForms() {
    for (const signal of signals.value) {
        formState(signal);
    }
}

/**
 * @param {{ id: string, present: boolean, severity: string }} signal
 */
function isDirty(signal) {
    const form = forms[signal.id];

    if (!form || form.saving) {
        return false;
    }

    return form.present !== signal.present || (form.present && form.severity !== signal.severity);
}

/**
 * @param {{ id: string, pupil_id: string, present: boolean, severity: string }} signal
 */
async function saveSignal(signal) {
    const form = formState(signal);
    form.saving = true;
    form.error = '';
    saveSuccess.value = '';

    // The API accepts exactly { present, severity }; when unflagging,
    // severity must be omitted (prohibited_if:present,false).
    const body = form.present ? { present: true, severity: form.severity } : { present: false };

    try {
        const response = await apiFetch(`/api/v1/pupils/${signal.pupil_id}/safeguarding-signal`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            skipForbiddenRedirect: true,
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 403 && payload.code === 'feature_not_available') {
            flagUnavailable.value = true;
            return;
        }

        if (!response.ok) {
            form.error = firstValidationMessage(payload)
                ?? (isRecord(payload) ? payload.message : '')
                ?? 'Unable to save safeguarding context.';
            return;
        }

        const saved = isRecord(payload.data) ? payload.data : null;
        saveSuccess.value = saved?.updated_at
            ? `Safeguarding context saved at ${new Date(saved.updated_at).toLocaleTimeString()}.`
            : 'Safeguarding context saved.';

        await loadSignals();
    } catch {
        form.error = 'Unable to save safeguarding context.';
    } finally {
        form.saving = false;
    }
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

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}
</script>
