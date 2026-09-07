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

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="drafts-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <Card
            v-else-if="drafts.length === 0"
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
            v-else
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
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';

const router = useRouter();

const drafts = ref([]);
const loading = ref(true);
const loadError = ref('');

onMounted(async () => {
    document.title = 'Drafts';
    await loadDrafts();
});

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

async function loadDrafts() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/drafts');

        if (!response.ok) {
            loadError.value = 'Unable to load Drafts.';

            return;
        }

        const payload = await response.json();
        drafts.value = asArray(payload.data).filter(hasPupil);
    } catch {
        loadError.value = 'Unable to load Drafts.';
    } finally {
        loading.value = false;
    }
}
</script>
