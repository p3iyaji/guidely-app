<template>
    <div data-testid="outputs-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Outputs</h1>
            <p class="mt-1 text-body text-text-muted">
                Download confirmed Documentation Outputs for Pupils in your School.
                Tribunal and inspection packs can be generated when they are available for this Tenant.
            </p>
        </div>

        <div
            class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
            data-testid="outputs-disclaimer"
            role="note"
        >
            Documentation evaluations support professional judgement. They are not diagnoses,
            funding decisions, or statutory determinations.
        </div>

        <Card
            v-if="isSenco"
            class="mt-6"
            data-testid="outputs-generate-form"
        >
            <h2 class="text-body font-semibold text-text">Generate an output</h2>
            <p class="mt-1 text-body text-text-muted">
                Generation requires a named confirmation. This is not an auto-issued statutory decision.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="generateOutput">
                <div>
                    <label class="block text-body text-text" for="output-pupil">Pupil</label>
                    <select
                        id="output-pupil"
                        v-model="generateForm.pupil_id"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="output-pupil"
                    >
                        <option value="">Select a Pupil</option>
                        <option
                            v-for="pupil in pupils"
                            :key="pupil.id"
                            :value="pupil.id"
                        >
                            {{ pupilName(pupil) }}
                        </option>
                    </select>
                    <p v-if="fieldErrors.pupil_id" class="mt-1 text-meta text-danger" data-testid="output-pupil-error">
                        {{ fieldErrors.pupil_id }}
                    </p>
                </div>

                <div>
                    <label class="block text-body text-text" for="output-review-cycle">Review Cycle</label>
                    <select
                        id="output-review-cycle"
                        v-model="generateForm.review_cycle_id"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="output-review-cycle"
                    >
                        <option value="">Select a Review Cycle</option>
                        <option
                            v-for="cycle in cyclesForSelectedPupil"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycleLabel(cycle) }}
                        </option>
                    </select>
                    <p v-if="fieldErrors.review_cycle_id" class="mt-1 text-meta text-danger" data-testid="output-review-cycle-error">
                        {{ fieldErrors.review_cycle_id }}
                    </p>
                </div>

                <div>
                    <label class="block text-body text-text" for="output-type">Type</label>
                    <select
                        id="output-type"
                        v-model="generateForm.type"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="output-type"
                    >
                        <option value="review_summary">Review summary</option>
                        <option value="ehcp_pack">EHCP pack</option>
                        <option
                            v-if="advancedPacksAvailable"
                            value="tribunal_pack"
                            data-testid="output-type-tribunal"
                        >
                            Tribunal pack
                        </option>
                        <option
                            v-if="advancedPacksAvailable"
                            value="inspection_pack"
                            data-testid="output-type-inspection"
                        >
                            Inspection pack
                        </option>
                    </select>
                    <p v-if="fieldErrors.type" class="mt-1 text-meta text-danger" data-testid="output-type-error">
                        {{ fieldErrors.type }}
                    </p>
                </div>

                <div v-if="isAdvancedType" data-testid="output-purpose-field">
                    <label class="block text-body text-text" for="output-purpose">Purpose</label>
                    <input
                        id="output-purpose"
                        v-model="generateForm.purpose"
                        type="text"
                        maxlength="2000"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="output-purpose"
                    >
                    <p v-if="fieldErrors.purpose" class="mt-1 text-meta text-danger" data-testid="output-purpose-error">
                        {{ fieldErrors.purpose }}
                    </p>
                </div>

                <p class="text-body text-text" data-testid="output-confirmer-name">
                    Confirmed by {{ confirmerName }}
                </p>

                <label class="flex items-start gap-2 text-body text-text">
                    <input
                        v-model="generateForm.disclaimer_acknowledged"
                        type="checkbox"
                        data-testid="output-disclaimer-ack"
                    >
                    <span>
                        I confirm that I, {{ confirmerName }}, have professionally reviewed this output
                        and acknowledge the disclaimer.
                    </span>
                </label>
                <p
                    v-if="fieldErrors.disclaimer_acknowledged || fieldErrors.confirmer_user_id"
                    class="text-meta text-danger"
                    role="alert"
                    data-testid="output-confirm-error"
                >
                    {{ fieldErrors.disclaimer_acknowledged || fieldErrors.confirmer_user_id }}
                </p>

                <p v-if="pupilsLoadError" class="text-body text-danger" role="alert" data-testid="outputs-pupils-error">
                    {{ pupilsLoadError }}
                </p>
                <p v-if="cyclesLoadError" class="text-body text-danger" role="alert" data-testid="outputs-cycles-error">
                    {{ cyclesLoadError }}
                </p>
                <p v-if="generateError" class="text-body text-danger" role="alert" data-testid="outputs-generate-error">
                    {{ generateError }}
                </p>

                <ButtonPrimary
                    type="submit"
                    :disabled="generating || !canGenerate"
                    data-testid="output-generate-submit"
                >
                    {{ generating ? 'Generating…' : 'Generate' }}
                </ButtonPrimary>
            </form>
        </Card>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="outputs-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="outputs-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <Card
            v-else-if="outputs.length === 0"
            class="mt-6"
            data-testid="outputs-empty"
        >
            <p class="text-body text-text" data-testid="outputs-empty-copy">
                No Documentation Outputs yet.
            </p>
        </Card>

        <ul
            v-else
            class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
            data-testid="outputs-list"
        >
            <li
                v-for="output in outputs"
                :key="output.id"
                data-testid="output-row"
            >
                <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <p class="text-body font-medium text-text" data-testid="output-type-label">
                            {{ output.type_label || typeLabel(output.type) }}
                            <span class="text-meta text-text-muted">v{{ output.version }}</span>
                        </p>
                        <p class="text-meta text-text-muted" data-testid="output-pupil-name">
                            {{ pupilName(output.pupil ?? output) }}
                        </p>
                        <p
                            v-if="isRecord(output.review_cycle)"
                            class="text-meta text-text-muted"
                            data-testid="output-review-cycle-label"
                        >
                            {{ cycleLabel(output.review_cycle) }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="output-confirmed-at">
                            Confirmed {{ formatConfirmedAt(output.confirmed_at) }}
                            <span v-if="isRecord(output.confirmer)"> by {{ output.confirmer.name }}</span>
                        </p>
                    </div>
                    <ButtonSecondary
                        type="button"
                        :disabled="downloadingId === output.id"
                        data-testid="output-download"
                        @click="downloadOutput(output)"
                    >
                        {{ downloadingId === output.id ? 'Downloading…' : 'Download' }}
                    </ButtonSecondary>
                </div>
            </li>
        </ul>

        <p
            v-if="downloadError"
            class="mt-4 text-body text-danger"
            role="alert"
            data-testid="outputs-download-error"
        >
            {{ downloadError }}
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const session = useSession();
const isSenco = computed(() => session.role.value === 'senco');
const confirmerName = computed(() => session.user.value?.name || 'the signed-in User');

const outputs = ref([]);
const pupils = ref([]);
const cycles = ref([]);
const loading = ref(true);
const loadError = ref('');
const generating = ref(false);
const generateError = ref('');
const pupilsLoadError = ref('');
const cyclesLoadError = ref('');
const downloadError = ref('');
const downloadingId = ref('');
const advancedPacksAvailable = ref(false);
const fieldErrors = reactive({
    pupil_id: '',
    review_cycle_id: '',
    type: '',
    purpose: '',
    confirmer_user_id: '',
    disclaimer_acknowledged: '',
});
const generateForm = reactive({
    pupil_id: '',
    review_cycle_id: '',
    type: 'review_summary',
    purpose: '',
    disclaimer_acknowledged: false,
});

const cyclesForSelectedPupil = computed(() => {
    return cycles.value.filter((cycle) => cycle.pupil_id === generateForm.pupil_id);
});

const isAdvancedType = computed(() => {
    return generateForm.type === 'tribunal_pack' || generateForm.type === 'inspection_pack';
});

const canGenerate = computed(() => {
    return Boolean(
        generateForm.disclaimer_acknowledged
        && generateForm.pupil_id
        && generateForm.review_cycle_id
        && (!isAdvancedType.value || generateForm.purpose.trim()),
    );
});

onMounted(async () => {
    await Promise.all([loadOutputs(), loadGenerateOptionsIfSenco()]);
});

watch(
    () => generateForm.type,
    (type) => {
        if (type !== 'tribunal_pack' && type !== 'inspection_pack') {
            generateForm.purpose = '';
            fieldErrors.purpose = '';
        }
    },
);

watch(
    () => generateForm.pupil_id,
    async (pupilId) => {
        generateForm.review_cycle_id = '';
        cycles.value = [];
        cyclesLoadError.value = '';

        if (!pupilId) {
            return;
        }

        await loadCyclesForPupil(pupilId);
    },
);

async function loadOutputs() {
    loading.value = true;
    loadError.value = '';
    outputs.value = [];

    try {
        const response = await apiFetch('/api/v1/documentation-outputs');

        if (response.status === 403) {
            loadError.value = 'You don’t have access.';

            return;
        }

        if (!response.ok) {
            loadError.value = 'Unable to load Outputs.';

            return;
        }

        const payload = await response.json();
        outputs.value = asArray(payload.data).filter(isRecord);
    } catch {
        loadError.value = 'Unable to load Outputs.';
    } finally {
        loading.value = false;
    }
}

async function loadGenerateOptionsIfSenco() {
    if (!isSenco.value) {
        return;
    }

    pupilsLoadError.value = '';
    await probeAdvancedPacks();

    try {
        const pupilsResponse = await apiFetch('/api/v1/pupils');

        if (!pupilsResponse.ok) {
            pupilsLoadError.value = 'Unable to load Pupils.';

            return;
        }

        const payload = await pupilsResponse.json();
        pupils.value = asArray(payload.data).filter(isRecord);
    } catch {
        pupils.value = [];
        pupilsLoadError.value = 'Unable to load Pupils.';
    }
}

async function probeAdvancedPacks() {
    advancedPacksAvailable.value = false;

    try {
        const response = await apiFetch('/api/v1/advanced-documentation-packs');
        advancedPacksAvailable.value = response.status === 200;
    } catch {
        advancedPacksAvailable.value = false;
    }
}

async function loadCyclesForPupil(pupilId) {
    cyclesLoadError.value = '';

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/review-cycles`);

        if (generateForm.pupil_id !== pupilId) {
            return;
        }

        if (!response.ok) {
            cycles.value = [];
            cyclesLoadError.value = 'Unable to load Review Cycles.';

            return;
        }

        const payload = await response.json();
        cycles.value = asArray(payload.data).filter(isRecord);
    } catch {
        if (generateForm.pupil_id !== pupilId) {
            return;
        }

        cycles.value = [];
        cyclesLoadError.value = 'Unable to load Review Cycles.';
    }
}

async function generateOutput() {
    generating.value = true;
    generateError.value = '';
    fieldErrors.pupil_id = '';
    fieldErrors.review_cycle_id = '';
    fieldErrors.type = '';
    fieldErrors.purpose = '';
    fieldErrors.confirmer_user_id = '';
    fieldErrors.disclaimer_acknowledged = '';

    if (!generateForm.disclaimer_acknowledged) {
        fieldErrors.disclaimer_acknowledged = 'Disclaimer acknowledgement is required.';
        generating.value = false;

        return;
    }

    if (isAdvancedType.value && !generateForm.purpose.trim()) {
        fieldErrors.purpose = 'A purpose is required for tribunal and inspection packs.';
        generating.value = false;

        return;
    }

    try {
        const response = await apiFetch('/api/v1/documentation-outputs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pupil_id: generateForm.pupil_id,
                review_cycle_id: generateForm.review_cycle_id,
                type: generateForm.type,
                confirmer_user_id: session.user.value?.id ?? null,
                disclaimer_acknowledged: true,
                ...(isAdvancedType.value ? { purpose: generateForm.purpose.trim() } : {}),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && isRecord(payload.errors)) {
                for (const [field, messages] of Object.entries(payload.errors)) {
                    if (field in fieldErrors) {
                        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
                    }
                }
            }

            generateError.value = payload.message ?? 'Unable to generate output.';

            return;
        }

        generateForm.disclaimer_acknowledged = false;
        generateForm.purpose = '';
        await loadOutputs();
    } catch {
        generateError.value = 'Unable to generate output.';
    } finally {
        generating.value = false;
    }
}

/**
 * @param {unknown} pupil
 */
function pupilName(pupil) {
    if (!isRecord(pupil)) {
        return 'Pupil';
    }

    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
}

/**
 * @param {Record<string, unknown>} cycle
 */
function cycleLabel(cycle) {
    const status = cycle.status === 'closed' ? ' · Closed' : '';

    return `${typeLabel(cycle.type)} · ${formatDueOn(cycle.due_on)}${status}`;
}

/**
 * @param {string|undefined} type
 */
function typeLabel(type) {
    const labels = {
        review_summary: 'Review summary',
        ehcp_pack: 'EHCP pack',
        tribunal_pack: 'Tribunal pack',
        inspection_pack: 'Inspection pack',
        annual_review: 'Annual Review',
        interim: 'Interim',
        other: 'Other',
    };

    return labels[type] ?? (type || '—');
}

/**
 * @param {string|undefined} dueOn
 */
function formatDueOn(dueOn) {
    if (!dueOn) {
        return '—';
    }

    const parsed = new Date(`${dueOn}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return dueOn;
    }

    return parsed.toLocaleDateString('en-GB', {
        dateStyle: 'medium',
        timeZone: 'Europe/London',
    });
}

/**
 * @param {string|undefined} confirmedAt
 */
function formatConfirmedAt(confirmedAt) {
    if (!confirmedAt) {
        return '—';
    }

    const parsed = new Date(confirmedAt);

    if (Number.isNaN(parsed.getTime())) {
        return confirmedAt;
    }

    return parsed.toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/London',
    });
}

/**
 * @param {Record<string, unknown>} output
 */
async function downloadOutput(output) {
    if (downloadingId.value) {
        return;
    }

    downloadingId.value = String(output.id ?? '');
    downloadError.value = '';

    try {
        const response = await apiFetch(`/api/v1/documentation-outputs/${output.id}/download`);

        if (!response.ok) {
            downloadError.value = 'Unable to download output.';

            return;
        }

        const blob = await response.blob();

        if (blob.size === 0) {
            downloadError.value = 'Unable to download output.';

            return;
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const type = typeof output.type === 'string' ? output.type.replaceAll('_', '-') : 'output';
        const version = output.version ?? 1;
        link.href = url;
        link.download = `${type}-v${version}.zip`;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        window.setTimeout(() => {
            URL.revokeObjectURL(url);
            link.remove();
        }, 0);
    } catch {
        downloadError.value = 'Unable to download output.';
    } finally {
        downloadingId.value = '';
    }
}

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
function asArray(value) {
    return Array.isArray(value) ? value : [];
}
</script>
