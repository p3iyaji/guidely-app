<template>
    <div data-testid="audit-events-page">
        <PageHero
            eyebrow="Administration"
            title="Audit events"
            description="Review the immutable activity history for this Tenant. Audit events cannot be created, edited, or deleted here."
        />

        <CrudSearch
            id="audit-events-search"
            v-model="searchQuery"
            placeholder="Search event, user, resource, or IP address"
            test-id="audit-events-search"
        />

        <div v-if="loading" class="space-y-3" data-testid="audit-events-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <p
            v-else-if="loadError"
            class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
            data-testid="audit-events-error"
            role="alert"
        >
            {{ loadError }}
        </p>

        <template v-else>
            <EmptyState
                v-if="events.length === 0"
                test-id="audit-events-empty"
            >
                {{ searchQuery.trim() === '' ? 'No audit events for this Tenant.' : 'No audit events match your search.' }}
            </EmptyState>

            <DataTable
                v-else
                test-id="audit-events-list"
            >
                <template #head>
                    <tr>
                        <th class="px-4 py-3" scope="col">Date and time</th>
                        <th class="px-4 py-3" scope="col">Event</th>
                        <th class="hidden px-4 py-3 md:table-cell" scope="col">User</th>
                        <th class="hidden px-4 py-3 lg:table-cell" scope="col">Resource</th>
                        <th class="px-4 py-3" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </template>
                <tr
                    v-for="event in events"
                    :key="event.id"
                    class="hover:bg-surface-muted/70"
                    data-testid="audit-event-row"
                >
                    <td class="whitespace-nowrap px-4 py-3 text-text-muted">
                        {{ formatDateTime(event.created_at) }}
                    </td>
                    <td class="px-4 py-3 font-mono text-meta text-text" data-testid="audit-event-type">
                        {{ event.event_type }}
                    </td>
                    <td class="hidden px-4 py-3 text-text-muted md:table-cell">
                        {{ event.user?.name || 'System' }}
                    </td>
                    <td class="hidden px-4 py-3 text-text-muted lg:table-cell">
                        {{ resourceLabel(event) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <TableAction
                            icon="open"
                            :label="`View ${event.event_type}`"
                            :data-testid="`audit-event-view-${event.id}`"
                            @click="openDetails(event)"
                        />
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="pagination.lastPage > 1"
                class="mt-4 flex items-center justify-between gap-4"
                data-testid="audit-events-pagination"
            >
                <ButtonSecondary
                    :disabled="pagination.currentPage <= 1 || loading"
                    data-testid="audit-events-previous"
                    @click="loadEvents(pagination.currentPage - 1)"
                >
                    Previous
                </ButtonSecondary>
                <span class="text-meta text-text-muted">
                    Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
                </span>
                <ButtonSecondary
                    :disabled="pagination.currentPage >= pagination.lastPage || loading"
                    data-testid="audit-events-next"
                    @click="loadEvents(pagination.currentPage + 1)"
                >
                    Next
                </ButtonSecondary>
            </div>
        </template>

        <Modal
            :open="selectedEvent !== null || detailLoading"
            title="Audit event details"
            data-testid="audit-event-details"
            @close="closeDetails"
        >
            <div v-if="detailLoading" class="space-y-3">
                <LoadingSkeleton variant="line" />
                <LoadingSkeleton variant="card" />
            </div>
            <p v-else-if="detailError" class="text-body text-danger" role="alert">
                {{ detailError }}
            </p>
            <dl v-else-if="selectedEvent" class="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-4 gap-y-3 text-body">
                <dt class="font-semibold text-text">Event</dt>
                <dd class="break-all font-mono text-meta text-text-muted">{{ selectedEvent.event_type }}</dd>
                <dt class="font-semibold text-text">Occurred</dt>
                <dd class="text-text-muted">{{ formatDateTime(selectedEvent.created_at) }}</dd>
                <dt class="font-semibold text-text">User</dt>
                <dd class="break-all text-text-muted">{{ userLabel(selectedEvent) }}</dd>
                <dt class="font-semibold text-text">Resource</dt>
                <dd class="break-all text-text-muted">{{ resourceLabel(selectedEvent) }}</dd>
                <dt class="font-semibold text-text">IP address</dt>
                <dd class="break-all text-text-muted">{{ selectedEvent.ip || '—' }}</dd>
                <dt class="font-semibold text-text">User agent</dt>
                <dd class="break-all text-text-muted">{{ selectedEvent.user_agent || '—' }}</dd>
                <dt class="font-semibold text-text">Metadata</dt>
                <dd>
                    <pre class="max-h-56 overflow-auto whitespace-pre-wrap break-all rounded-md bg-canvas p-3 font-mono text-meta text-text-muted">{{ formattedMetadata }}</pre>
                </dd>
            </dl>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { apiFetch } from '../api/client';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import CrudSearch from '../shared/ui/CrudSearch.vue';
import DataTable from '../shared/ui/DataTable.vue';
import EmptyState from '../shared/ui/EmptyState.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';
import PageHero from '../shared/ui/PageHero.vue';
import TableAction from '../shared/ui/TableAction.vue';

const events = ref([]);
const loading = ref(true);
const loadError = ref('');
const searchQuery = ref('');
const selectedEvent = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
});
let searchTimer = null;
let loadSequence = 0;

const formattedMetadata = computed(() => {
    const metadata = selectedEvent.value?.metadata;

    if (metadata == null || Object.keys(metadata).length === 0) {
        return '—';
    }

    return JSON.stringify(metadata, null, 2);
});

onMounted(async () => {
    await loadEvents();
});

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

watch(searchQuery, () => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        loadEvents();
    }, 250);
});

async function loadEvents(page = 1) {
    const sequence = ++loadSequence;
    loading.value = true;
    loadError.value = '';
    const parameters = new URLSearchParams({ page: String(page) });
    const query = searchQuery.value.trim();

    if (query !== '') {
        parameters.set('q', query);
    }

    try {
        const response = await apiFetch(`/api/v1/audit-events?${parameters.toString()}`);

        if (sequence !== loadSequence) {
            return;
        }

        if (!response.ok) {
            loadError.value = response.status === 403
                ? 'You don’t have access.'
                : 'Unable to load audit events.';
            events.value = [];

            return;
        }

        const payload = await response.json();
        events.value = Array.isArray(payload.data) ? payload.data.filter(isRecord) : [];
        pagination.value = {
            currentPage: Number(payload.meta?.current_page) || 1,
            lastPage: Number(payload.meta?.last_page) || 1,
        };
    } catch {
        if (sequence === loadSequence) {
            loadError.value = 'Unable to load audit events.';
            events.value = [];
        }
    } finally {
        if (sequence === loadSequence) {
            loading.value = false;
        }
    }
}

async function openDetails(event) {
    detailLoading.value = true;
    detailError.value = '';
    selectedEvent.value = event;

    try {
        const response = await apiFetch(`/api/v1/audit-events/${event.id}`);

        if (!response.ok) {
            detailError.value = 'Unable to load audit event details.';

            return;
        }

        const payload = await response.json();

        if (isRecord(payload.data)) {
            selectedEvent.value = payload.data;
        }
    } catch {
        detailError.value = 'Unable to load audit event details.';
    } finally {
        detailLoading.value = false;
    }
}

function closeDetails() {
    selectedEvent.value = null;
    detailError.value = '';
    detailLoading.value = false;
}

function formatDateTime(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    }).format(date);
}

function resourceLabel(event) {
    const type = event.resource_type || '';
    const id = event.resource_id || '';

    if (type === '' && id === '') {
        return '—';
    }

    return [type, id].filter(Boolean).join(' · ');
}

function userLabel(event) {
    if (event.user?.name) {
        return event.user.email
            ? `${event.user.name} · ${event.user.email}`
            : event.user.name;
    }

    return event.user_id || 'System';
}

function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}
</script>
