<template>
    <div data-testid="drafts-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Drafts</h1>
            <p class="mt-1 text-body text-text-muted">
                Open a draft to continue editing or submit it when the required fields are complete.
            </p>
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="drafts-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <template v-else>
            <Card
                v-if="loadError"
                class="mt-6"
                data-testid="drafts-error"
            >
                <p class="text-body text-danger" role="alert">{{ loadError }}</p>
            </Card>

            <Card
                v-if="localDrafts.length > 0"
                class="mt-6"
                data-testid="drafts-local-queue"
            >
                <h2 class="text-body font-semibold text-text">On this device</h2>
                <p
                    class="mt-2 text-body text-text"
                    data-testid="drafts-offline-banner"
                    role="status"
                >
                    {{ offlineBanner }}
                </p>
                <ul class="mt-4 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface">
                    <li
                        v-for="item in localDrafts"
                        :key="item.id"
                        class="px-4 py-3"
                        data-testid="drafts-local-row"
                    >
                        <p class="text-body font-medium text-text" data-testid="drafts-local-type">
                            {{ typeLabel(item.type) }}
                        </p>
                        <p class="text-meta text-text-muted" data-testid="drafts-local-pupil">
                            {{ item.pupilLabel || 'Pupil' }}
                        </p>
                        <p class="mt-1 text-meta text-text-muted" data-testid="drafts-local-id">
                            Device reference: {{ item.id }}
                        </p>
                        <p
                            v-if="item.status === 'pupil_conflict' || item.lastError"
                            class="mt-2 text-body text-danger"
                            data-testid="drafts-local-conflict"
                            role="alert"
                        >
                            {{ item.lastError || pupilConflictMessage }}
                        </p>
                    </li>
                </ul>
            </Card>

            <Card
                v-if="!loadError && drafts.length === 0 && localDrafts.length === 0"
                class="mt-6"
                data-testid="drafts-empty"
            >
                <p class="text-body text-text">No drafts yet.</p>
                <div class="mt-4">
                    <ButtonPrimary
                        class="min-h-11"
                        data-testid="drafts-capture-cta"
                        @click="goToCapture"
                    >
                        Capture Evidence
                    </ButtonPrimary>
                </div>
            </Card>

            <ul
                v-if="drafts.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="drafts-list"
            >
                <li
                    v-for="draft in drafts"
                    :key="draft.id"
                    data-testid="draft-row"
                >
                    <RouterLink
                        :to="{ name: 'capture', query: { draft: draft.id } }"
                        class="flex min-h-11 flex-col gap-2 px-4 py-3 text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring sm:flex-row sm:items-center sm:justify-between"
                        :data-testid="`draft-row-link-${draft.id}`"
                    >
                        <div class="min-w-0">
                            <p class="text-body font-medium text-text" data-testid="draft-type">
                                {{ typeLabel(draft.type) }}
                            </p>
                            <p class="text-meta text-text-muted" data-testid="draft-pupil">
                                {{ pupilName(draft.pupil) }}
                            </p>
                            <p
                                v-if="draft.author?.name"
                                class="text-meta text-text-muted"
                                data-testid="draft-author"
                            >
                                Author: {{ draft.author.name }}
                            </p>
                        </div>
                        <p class="text-meta text-text-muted" data-testid="draft-occurred-at">
                            {{ formatOccurredAt(draft.occurred_at) }}
                        </p>
                    </RouterLink>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import {
    OFFLINE_DRAFT_BANNER,
    PUPIL_IDENTITY_CONFLICT_MESSAGE,
} from '../features/evidence/offlineBanner';
import {
    listOfflineDrafts,
    OFFLINE_QUEUE_CHANGED_EVENT,
} from '../features/evidence/offlineDraftQueue';
import { flushOfflineDrafts } from '../features/evidence/flushOfflineDrafts';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const router = useRouter();

const drafts = ref([]);
const localDrafts = ref([]);
const loading = ref(true);
const loadError = ref('');
const offlineBanner = OFFLINE_DRAFT_BANNER;
const pupilConflictMessage = PUPIL_IDENTITY_CONFLICT_MESSAGE;

onMounted(async () => {
    document.title = 'Drafts';
    window.addEventListener('online', onOnline);
    window.addEventListener(OFFLINE_QUEUE_CHANGED_EVENT, onQueueChanged);
    await loadAll();
});

onUnmounted(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener(OFFLINE_QUEUE_CHANGED_EVENT, onQueueChanged);
});

async function onOnline() {
    await flushOfflineDrafts();
    await Promise.all([loadDrafts(), loadLocalDrafts()]);
}

async function onQueueChanged() {
    await loadLocalDrafts();
}

/**
 * @param {string|undefined} type
 */
function typeLabel(type) {
    if (type === 'intervention') {
        return 'Intervention';
    }

    if (type === 'response') {
        return 'Pupil Response';
    }

    return 'Observation';
}

/**
 * @param {{ given_name?: string, family_name?: string }|null|undefined} pupil
 */
function pupilName(pupil) {
    if (!pupil) {
        return 'Pupil';
    }

    return `${pupil.given_name ?? ''} ${pupil.family_name ?? ''}`.trim() || 'Pupil';
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

function goToCapture() {
    router.push({ name: 'capture' });
}

/**
 * @param {unknown} value
 * @returns {array}
 */
function asArray(value) {
    return Array.isArray(value) ? value : [];
}

/**
 * @param {unknown} item
 * @returns {boolean}
 */
function hasPupil(item) {
    return Boolean(item && typeof item === 'object' && item.pupil && item.pupil.id);
}

async function loadLocalDrafts() {
    localDrafts.value = await listOfflineDrafts();
}

async function loadDrafts() {
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/drafts');

        if (!response.ok) {
            loadError.value = 'Unable to load Drafts.';
            drafts.value = [];

            return;
        }

        const payload = await response.json();
        drafts.value = asArray(payload.data).filter(hasPupil);
    } catch {
        loadError.value = 'Unable to load Drafts.';
        drafts.value = [];
    }
}

async function loadAll() {
    loading.value = true;

    try {
        await Promise.all([loadDrafts(), loadLocalDrafts()]);
    } finally {
        loading.value = false;
    }
}
</script>
