<template>
    <section
        class="mx-auto w-full max-w-lg rounded-lg border border-border bg-surface p-6 shadow-[0_1px_3px_rgba(31,41,55,0.08)]"
        role="alert"
        data-testid="access-denied"
    >
        <h1 class="text-heading font-semibold text-text">{{ heading }}</h1>
        <p class="mt-2 text-body text-text-muted">
            {{ body }}
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <RouterLink
                to="/"
                class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-body font-semibold text-primary-foreground hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="access-denied-home"
            >
                Back to dashboard
            </RouterLink>
            <RouterLink
                v-if="isTenantAdmin"
                to="/pupils"
                class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-4 py-2 text-body font-semibold text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                data-testid="access-denied-pupils"
            >
                Open pupils
            </RouterLink>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useSession } from '../features/auth/session';

const heading = 'You don’t have access';
const session = useSession();
const isTenantAdmin = computed(() => session.role.value === 'tenant_admin');
const body = computed(() => {
    if (isTenantAdmin.value) {
        return 'This area is for other roles. You can manage Pupil working records from Pupils, or return to the dashboard.';
    }

    return 'You do not have permission to view this page. Contact your Tenant Admin if you need access.';
});
</script>
