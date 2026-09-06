<template>
    <div data-testid="home-dashboard">
        <h1 class="text-heading font-semibold text-text">{{ title }}</h1>
        <p class="mt-1 text-body text-text-muted">
            Overview for your Role. Counts will update as product surfaces come online.
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="kpi-row">
            <KpiCard
                v-for="kpi in kpis"
                :key="kpi.label"
                :label="kpi.label"
                :value="kpi.value"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSession } from '../features/auth/session';
import KpiCard from '../shared/ui/KpiCard.vue';

const session = useSession();

const title = computed(() => {
    const role = session.role.value;

    if (role === 'tenant_admin') {
        return 'Tenant Admin';
    }

    if (role === 'trust_send_lead' || role === 'trust_executive') {
        return 'Trust overview';
    }

    return 'Dashboard';
});

/** Placeholder KPI row ready for later live counts (UX-DR6). */
const kpis = [
    { label: 'Pupils in scope', value: null },
    { label: 'Open gaps', value: null },
    { label: 'Review cycles due', value: null },
    { label: 'Drafts', value: null },
];
</script>
