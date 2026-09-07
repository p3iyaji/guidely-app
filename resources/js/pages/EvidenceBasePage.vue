<template>
    <div data-testid="evidence-base-page">
        <div v-if="loading" class="space-y-3" data-testid="evidence-base-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text" data-testid="evidence-base-title">
                    {{ pupilName }}
                </h1>
                <p
                    v-if="pupilSubtitle"
                    class="mt-1 text-body text-text-muted"
                    data-testid="evidence-base-subtitle"
                >
                    {{ pupilSubtitle }}
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="evidence-base-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <p
                v-if="loadError"
                class="mt-4 text-body text-danger"
                data-testid="evidence-base-error"
                role="alert"
            >
                {{ loadError }}
            </p>

            <div
                class="mt-6 flex flex-wrap gap-2"
                role="group"
                aria-label="Evidence filters"
                data-testid="evidence-base-filters"
            >
                <button
                    v-for="chip in filterChips"
                    :key="chip.value"
                    type="button"
                    class="min-h-11 rounded-md border px-3 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :class="activeFilter === chip.value
                        ? 'border-border bg-surface-muted font-semibold text-text'
                        : 'border-border bg-surface text-text-muted'"
                    :aria-pressed="activeFilter === chip.value ? 'true' : 'false'"
                    :data-testid="`evidence-filter-${chip.value || 'all'}`"
                    @click="setFilter(chip.value)"
                >
                    {{ chip.label }}
                </button>
            </div>

            <Card
                v-if="!loadError && records.length === 0"
                class="mt-6"
                data-testid="evidence-base-empty"
            >
                <p class="text-body text-text" data-testid="evidence-base-empty-copy">
                    {{ emptyCopy }}
                </p>
                <div
                    v-if="showCaptureCta"
                    class="mt-4"
                >
                    <ButtonPrimary
                        class="min-h-11"
                        data-testid="evidence-base-capture-cta"
                        @click="goToCapture"
                    >
                        Capture Evidence
                    </ButtonPrimary>
                </div>
            </Card>

            <ul
                v-else-if="records.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="evidence-base-list"
            >
                <li
                    v-for="record in records"
                    :key="record.id"
                    class="px-4 py-3"
                    data-testid="evidence-row"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-text" data-testid="evidence-type">
                                {{ typeLabel(record) }}
                            </p>
                            <p
                                v-if="record.author?.name"
                                class="text-meta text-text-muted"
                                data-testid="evidence-author"
                            >
                                {{ record.author.name }}
                            </p>
                            <p
                                v-if="termLabel(record)"
                                class="mt-1 text-meta text-text-muted"
                                data-testid="evidence-term"
                            >
                                {{ termLabel(record) }}
                            </p>
                            <p
                                v-if="record.body"
                                class="mt-2 text-body text-text"
                                data-testid="evidence-body"
                            >
                                {{ record.body }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                            <p class="text-meta text-text-muted" data-testid="evidence-occurred-at">
                                {{ formatOccurredAt(record.occurred_at) }}
                            </p>
                            <ButtonOutline
                                v-if="canAmend(record)"
                                class="min-h-11"
                                data-testid="evidence-amend-open"
                                @click="openAmend(record)"
                            >
                                Amend
                            </ButtonOutline>
                        </div>
                    </div>

                    <div
                        v-if="amendingId === record.id"
                        class="mt-4 space-y-4 border-t border-border pt-4"
                        data-testid="evidence-amend-panel"
                    >
                        <h2 class="text-body font-semibold text-text">Amend Evidence</h2>

                        <p
                            v-if="amendError"
                            class="text-body text-danger"
                            data-testid="evidence-amend-error"
                            role="alert"
                        >
                            {{ amendError }}
                        </p>

                        <form class="space-y-3" @submit.prevent="saveAmend(record)">
                            <div>
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-occurred-at-${record.id}`"
                                >Session date and time</label>
                                <input
                                    :id="`amend-occurred-at-${record.id}`"
                                    v-model="amendForm.occurred_at_local"
                                    type="datetime-local"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-occurred-at"
                                >
                                <p
                                    v-if="amendFieldErrors.occurred_at"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-occurred-at-error"
                                >
                                    {{ amendFieldErrors.occurred_at }}
                                </p>
                            </div>

                            <div v-if="record.type === 'observation'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-setting-${record.id}`"
                                >Setting</label>
                                <select
                                    :id="`amend-setting-${record.id}`"
                                    v-model="amendForm.setting_term_id"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-setting"
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
                                    v-if="amendFieldErrors.setting_term_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-setting-error"
                                >
                                    {{ amendFieldErrors.setting_term_id }}
                                </p>
                            </div>

                            <div v-if="record.type === 'intervention'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-provision-${record.id}`"
                                >Provision</label>
                                <select
                                    :id="`amend-provision-${record.id}`"
                                    v-model="amendForm.provision_term_id"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-provision"
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
                                    v-if="amendFieldErrors.provision_term_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-provision-error"
                                >
                                    {{ amendFieldErrors.provision_term_id }}
                                </p>
                            </div>

                            <div v-if="record.type === 'response'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-related-intervention-${record.id}`"
                                >Related Intervention (optional)</label>
                                <select
                                    :id="`amend-related-intervention-${record.id}`"
                                    v-model="amendForm.related_intervention_id"
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-related-intervention"
                                >
                                    <option value="">None</option>
                                    <option
                                        v-for="item in relatedInterventions"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ interventionOptionLabel(item) }}
                                    </option>
                                </select>
                                <p
                                    v-if="amendFieldErrors.related_intervention_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-related-intervention-error"
                                >
                                    {{ amendFieldErrors.related_intervention_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-body-${record.id}`"
                                >Notes</label>
                                <textarea
                                    :id="`amend-body-${record.id}`"
                                    v-model="amendForm.body"
                                    rows="4"
                                    :required="record.type !== 'intervention'"
                                    class="mt-1 w-full max-w-2xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-body"
                                />
                                <p
                                    v-if="amendFieldErrors.body"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-body-error"
                                >
                                    {{ amendFieldErrors.body }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <ButtonPrimary
                                    class="min-h-11"
                                    :disabled="amendSaving"
                                    data-testid="evidence-amend-save"
                                    type="submit"
                                >
                                    {{ amendSaving ? 'Saving…' : 'Save amendment' }}
                                </ButtonPrimary>
                                <ButtonOutline
                                    class="min-h-11"
                                    :disabled="amendSaving"
                                    data-testid="evidence-amend-cancel"
                                    type="button"
                                    @click="closeAmend"
                                >
                                    Cancel
                                </ButtonOutline>
                            </div>
                        </form>

                        <div data-testid="evidence-versions-panel">
                            <h3 class="text-body font-semibold text-text">Previous versions</h3>
                            <p
                                v-if="versionsLoading"
                                class="mt-2 text-meta text-text-muted"
                                data-testid="evidence-versions-loading"
                            >
                                Loading previous versions…
                            </p>
                            <p
                                v-else-if="versionsError"
                                class="mt-2 text-body text-danger"
                                data-testid="evidence-versions-error"
                                role="alert"
                            >
                                {{ versionsError }}
                            </p>
                            <p
                                v-else-if="versions.length === 0"
                                class="mt-2 text-meta text-text-muted"
                                data-testid="evidence-versions-empty"
                            >
                                No previous versions yet.
                            </p>
                            <ul
                                v-else
                                class="mt-2 space-y-3"
                                data-testid="evidence-versions-list"
                            >
                                <li
                                    v-for="version in versions"
                                    :key="version.id"
                                    class="rounded-md border border-border px-3 py-2"
                                    data-testid="evidence-version-row"
                                >
                                    <p class="text-meta font-medium text-text">
                                        Version {{ version.version }}
                                        <span class="font-normal text-text-muted">
                                            · {{ formatOccurredAt(version.superseded_at) }}
                                            <template v-if="version.superseded_by?.name">
                                                · {{ version.superseded_by.name }}
                                            </template>
                                        </span>
                                    </p>
                                    <p
                                        v-if="version.snapshot?.body"
                                        class="mt-1 text-body text-text"
                                        data-testid="evidence-version-body"
                                    >
                                        {{ version.snapshot.body }}
                                    </p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const filterChips = [
    { value: '', label: 'All' },
    { value: 'observation', label: 'Observation' },
    { value: 'intervention', label: 'Intervention' },
    { value: 'response', label: 'Pupil Response' },
    { value: 'review_note', label: 'Review note' },
    { value: 'import', label: 'Import' },
];

const DEFAULT_DOCUMENT_TITLE = 'GuidelyEdu';

const session = useSession();
const route = useRoute();
const router = useRouter();

const pupil = ref(null);
const records = ref([]);
const loading = ref(true);
const loadError = ref('');
const activeFilter = ref('');
const hasAnySubmitted = ref(false);
let loadSeq = 0;

const amendingId = ref('');
const amendSaving = ref(false);
const amendError = ref('');
const amendForm = reactive({
    occurred_at_local: '',
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});
const amendFieldErrors = reactive({
    occurred_at: '',
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});
const settingTerms = ref([]);
const provisionTerms = ref([]);
const relatedInterventions = ref([]);
const ontologyLoaded = ref(false);
const versions = ref([]);
const versionsLoading = ref(false);
const versionsError = ref('');

const isTeacher = computed(() => session.role.value === 'teacher');

const pupilName = computed(() => {
    if (!pupil.value) {
        return 'Evidence Base';
    }

    return `${pupil.value.given_name ?? ''} ${pupil.value.family_name ?? ''}`.trim() || 'Evidence Base';
});

const pupilSubtitle = computed(() => {
    if (!pupil.value) {
        return '';
    }

    const parts = [];

    if (pupil.value.year_group) {
        parts.push(pupil.value.year_group);
    }

    if (pupil.value.send_status === 'sen_support') {
        parts.push('SEN Support');
    } else if (pupil.value.send_status === 'ehcp') {
        parts.push('EHCP');
    }

    return parts.join(' · ');
});

const emptyCopy = computed(() => {
    if (activeFilter.value && hasAnySubmitted.value) {
        return 'No Evidence Records match this filter.';
    }

    return 'No Evidence Records yet.';
});

const showCaptureCta = computed(() => {
    return isTeacher.value
        && !loadError.value
        && records.value.length === 0
        && !activeFilter.value
        && !hasAnySubmitted.value;
});

watch(
    pupilName,
    (title) => {
        document.title = title;

        if (route.name === 'pupil-detail') {
            route.meta.title = title;
        }
    },
    { immediate: true },
);

watch(
    () => route.params.id,
    async (id) => {
        if (!id) {
            pupil.value = null;
            records.value = [];
            hasAnySubmitted.value = false;
            loadError.value = 'Unable to load Evidence Base.';
            loading.value = false;

            return;
        }

        await loadPage();
    },
);

onMounted(async () => {
    await loadPage();
});

onUnmounted(() => {
    document.title = DEFAULT_DOCUMENT_TITLE;
});

/**
 * @param {string} value
 */
async function setFilter(value) {
    if (activeFilter.value === value) {
        return;
    }

    activeFilter.value = value;
    closeAmend();
    await loadEvidence({ seq: loadSeq });
}

function goToCapture() {
    router.push({ name: 'capture' });
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

/**
 * @param {{ type?: string, lifecycle?: string, source?: string|null, author_id?: string, author?: { id?: string } }} record
 */
function canAmend(record) {
    const role = session.role.value;

    if (record.lifecycle !== 'submitted') {
        return false;
    }

    if (!['observation', 'intervention', 'response'].includes(record.type ?? '')) {
        return false;
    }

    if (role === 'senco') {
        return true;
    }

    if (role !== 'teacher' && role !== 'support_staff') {
        return false;
    }

    const authorId = record.author_id ?? record.author?.id;

    return authorId != null && String(authorId) === String(session.user.value?.id ?? '');
}

/**
 * @param {{ type?: string, source?: string|null }} record
 */
function typeLabel(record) {
    if (record.source === 'import') {
        const base = typeLabelFromType(record.type);

        return `${base} (Import)`;
    }

    return typeLabelFromType(record.type);
}

/**
 * @param {string|undefined} type
 */
function typeLabelFromType(type) {
    if (type === 'intervention') {
        return 'Intervention';
    }

    if (type === 'response') {
        return 'Pupil Response';
    }

    if (type === 'review_note') {
        return 'Review note';
    }

    if (type === 'observation') {
        return 'Observation';
    }

    return type ? String(type) : 'Unknown';
}

/**
 * @param {{ setting?: { label?: string }|null, provision?: { label?: string }|null, related_intervention?: { provision?: { label?: string }|null }|null }} record
 */
function termLabel(record) {
    if (record.setting?.label) {
        return record.setting.label;
    }

    if (record.provision?.label) {
        return record.provision.label;
    }

    if (record.related_intervention?.provision?.label) {
        return `Related: ${record.related_intervention.provision.label}`;
    }

    return '';
}

/**
 * @param {{ occurred_at?: string, provision?: { label?: string }|null }} item
 */
function interventionOptionLabel(item) {
    const when = formatOccurredAt(item.occurred_at);
    const provision = item.provision?.label ? ` · ${item.provision.label}` : '';

    return `${when}${provision}`;
}

/**
 * @param {string|undefined} iso
 */
function formatOccurredAt(iso) {
    if (!iso) {
        return '—';
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

/**
 * @param {string|undefined} iso
 */
function toLocalDateTimeInput(iso) {
    if (!iso) {
        return '';
    }

    const parsed = new Date(iso);

    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const pad = (value) => String(value).padStart(2, '0');

    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
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

function clearAmendFieldErrors() {
    amendFieldErrors.occurred_at = '';
    amendFieldErrors.setting_term_id = '';
    amendFieldErrors.provision_term_id = '';
    amendFieldErrors.related_intervention_id = '';
    amendFieldErrors.body = '';
}

function closeAmend() {
    amendingId.value = '';
    amendError.value = '';
    amendSaving.value = false;
    versions.value = [];
    versionsError.value = '';
    versionsLoading.value = false;
    relatedInterventions.value = [];
    clearAmendFieldErrors();
}

/**
 * @param {Record<string, unknown>} record
 */
async function openAmend(record) {
    if (amendingId.value === record.id) {
        closeAmend();

        return;
    }

    amendingId.value = String(record.id);
    amendError.value = '';
    clearAmendFieldErrors();
    amendForm.occurred_at_local = toLocalDateTimeInput(String(record.occurred_at ?? ''));
    amendForm.setting_term_id = record.setting?.id
        ? String(record.setting.id)
        : (record.setting_term_id ? String(record.setting_term_id) : '');
    amendForm.provision_term_id = record.provision?.id
        ? String(record.provision.id)
        : (record.provision_term_id ? String(record.provision_term_id) : '');
    amendForm.related_intervention_id = record.related_intervention_id
        ? String(record.related_intervention_id)
        : '';
    amendForm.body = record.body != null ? String(record.body) : '';

    await Promise.all([
        ensureOntologyTerms(),
        loadVersions(String(record.id)),
        record.type === 'response'
            ? loadRelatedInterventions(String(record.pupil_id ?? route.params.id ?? ''))
            : Promise.resolve(),
    ]);
}

async function ensureOntologyTerms() {
    if (ontologyLoaded.value) {
        return;
    }

    try {
        const [settingsResponse, provisionsResponse] = await Promise.all([
            apiFetch('/api/v1/ontology/setting-terms'),
            apiFetch('/api/v1/ontology/provision-terms'),
        ]);

        if (!settingsResponse.ok || !provisionsResponse.ok) {
            amendError.value = 'Unable to load Ontology terms for amending Evidence.';

            return;
        }

        const settingsPayload = await settingsResponse.json();
        const provisionsPayload = await provisionsResponse.json();
        settingTerms.value = asArray(settingsPayload.data).filter(isRecord);
        provisionTerms.value = asArray(provisionsPayload.data).filter(isRecord);
        ontologyLoaded.value = true;
    } catch {
        amendError.value = 'Unable to load Ontology terms for amending Evidence.';
    }
}

/**
 * @param {string} pupilId
 */
async function loadRelatedInterventions(pupilId) {
    relatedInterventions.value = [];

    if (pupilId === '') {
        amendError.value = 'Unable to load Interventions for this Pupil.';

        return;
    }

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/interventions`);

        if (!response.ok) {
            amendError.value = 'Unable to load Interventions for this Pupil.';

            return;
        }

        const payload = await response.json();
        relatedInterventions.value = asArray(payload.data).filter(isRecord);
    } catch {
        relatedInterventions.value = [];
        amendError.value = 'Unable to load Interventions for this Pupil.';
    }
}

/**
 * @param {string} evidenceId
 */
async function loadVersions(evidenceId) {
    versionsLoading.value = true;
    versionsError.value = '';
    versions.value = [];

    try {
        const response = await apiFetch(`/api/v1/evidence/${evidenceId}/versions`);

        if (amendingId.value !== evidenceId) {
            return;
        }

        if (!response.ok) {
            versionsError.value = 'Unable to load previous versions.';

            return;
        }

        const payload = await response.json();
        versions.value = asArray(payload.data).filter(isRecord);
    } catch {
        if (amendingId.value !== evidenceId) {
            return;
        }

        versionsError.value = 'Unable to load previous versions.';
    } finally {
        if (amendingId.value === evidenceId) {
            versionsLoading.value = false;
        }
    }
}

/**
 * @param {Record<string, unknown>} record
 */
async function saveAmend(record) {
    amendSaving.value = true;
    amendError.value = '';
    clearAmendFieldErrors();

    const occurredAt = toUtcIso(amendForm.occurred_at_local);

    if (!occurredAt) {
        amendFieldErrors.occurred_at = 'Enter a valid session date and time.';
        amendSaving.value = false;

        return;
    }

    /** @type {Record<string, unknown>} */
    const body = {
        occurred_at: occurredAt,
        body: amendForm.body.trim() === '' ? null : amendForm.body.trim(),
    };

    if (record.type === 'observation') {
        body.setting_term_id = amendForm.setting_term_id || null;
    } else if (record.type === 'intervention') {
        body.provision_term_id = amendForm.provision_term_id || null;
    } else if (record.type === 'response') {
        body.related_intervention_id = amendForm.related_intervention_id || null;
    }

    try {
        const response = await apiFetch(`/api/v1/evidence/${record.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        if (response.status === 422) {
            const payload = await response.json();
            const errors = isRecord(payload.errors) ? payload.errors : {};
            amendFieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            amendFieldErrors.setting_term_id = errors.setting_term_id?.[0] ?? '';
            amendFieldErrors.provision_term_id = errors.provision_term_id?.[0] ?? '';
            amendFieldErrors.related_intervention_id = errors.related_intervention_id?.[0] ?? '';
            amendFieldErrors.body = errors.body?.[0] ?? '';
            amendError.value = 'Please correct the highlighted fields.';
            amendSaving.value = false;

            return;
        }

        if (!response.ok) {
            amendError.value = 'Unable to save this amendment.';
            amendSaving.value = false;

            return;
        }

        const payload = await response.json();
        const updated = isRecord(payload.data) ? payload.data : null;

        if (updated?.id) {
            records.value = records.value.map((row) => (
                row.id === updated.id ? { ...row, ...updated } : row
            ));
        }

        closeAmend();
        await loadEvidence({ seq: loadSeq });
    } catch {
        amendError.value = 'Unable to save this amendment.';
    } finally {
        amendSaving.value = false;
    }
}

async function loadPage() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    activeFilter.value = '';
    hasAnySubmitted.value = false;
    pupil.value = null;
    records.value = [];
    closeAmend();

    try {
        const [pupilOk, evidenceOk] = await Promise.all([
            loadPupil(seq),
            loadEvidence({ trackUnfiltered: true, seq }),
        ]);

        if (seq !== loadSeq) {
            return;
        }

        if (!pupilOk || !evidenceOk) {
            records.value = [];
            loadError.value = 'Unable to load Evidence Base.';
        }
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        records.value = [];
        loadError.value = 'Unable to load Evidence Base.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

/**
 * @param {number} seq
 * @returns {Promise<boolean>}
 */
async function loadPupil(seq) {
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}`);

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            pupil.value = null;

            return false;
        }

        const payload = await response.json();
        pupil.value = isRecord(payload.data) ? payload.data : null;

        return pupil.value != null;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        pupil.value = null;

        return false;
    }
}

/**
 * @param {{ trackUnfiltered?: boolean, seq?: number }} [options]
 * @returns {Promise<boolean>}
 */
async function loadEvidence(options = {}) {
    const { trackUnfiltered = false, seq = loadSeq } = options;
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    const query = activeFilter.value ? `?filter=${encodeURIComponent(activeFilter.value)}` : '';

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/evidence${query}`);

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            records.value = [];

            if (!trackUnfiltered) {
                loadError.value = 'Unable to load Evidence Base.';
            }

            return false;
        }

        const payload = await response.json();
        records.value = asArray(payload.data).filter(isRecord);

        if (trackUnfiltered || !activeFilter.value) {
            hasAnySubmitted.value = records.value.length > 0;
        }

        if (!trackUnfiltered) {
            loadError.value = '';
        }

        return true;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        records.value = [];

        if (!trackUnfiltered) {
            loadError.value = 'Unable to load Evidence Base.';
        }

        return false;
    }
}
</script>
