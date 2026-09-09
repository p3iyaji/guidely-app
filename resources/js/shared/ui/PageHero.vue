<template>
    <section class="mb-6 flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p
                v-if="eyebrow"
                class="mb-1 text-label font-semibold uppercase tracking-wider text-primary"
            >
                {{ eyebrow }}
            </p>
            <h1 class="font-display text-display font-bold text-text">{{ title }}</h1>
            <p
                v-if="description"
                class="mt-1 text-body text-text-muted"
            >
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.action"
            class="self-start sm:self-auto"
        >
            <slot name="action" />
        </div>
    </section>
</template>

<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>
