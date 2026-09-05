<template>
    <section
        class="mx-auto w-full max-w-md rounded-lg bg-surface border border-border p-6 shadow-[0_1px_3px_rgba(31,41,55,0.08)]"
        role="alert"
        data-testid="access-denied"
    >
        <h1 class="text-heading font-semibold text-text">{{ heading }}</h1>
        <p class="mt-2 text-body text-text-muted">
            You do not have permission to view this page. Contact your Tenant Admin if you need access.
        </p>
    </section>
</template>

<script setup>
const heading = 'You don’t have access';
</script>
