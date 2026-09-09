<template>
    <div
        class="overflow-hidden rounded-md border border-border bg-surface"
        :data-testid="testId"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-body">
                <thead class="border-b border-border bg-canvas text-meta font-semibold uppercase tracking-wide text-text-muted">
                    <slot name="head" />
                </thead>
                <tbody class="divide-y divide-border">
                    <slot />
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    testId: {
        type: String,
        required: true,
    },
});
</script>
