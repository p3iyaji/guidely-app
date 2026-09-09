<template>
    <Card
        class="mt-6 text-center"
        :data-testid="testId"
    >
        <p class="text-body text-text-muted">
            <slot />
        </p>
        <div
            v-if="$slots.actions"
            class="mt-4 flex flex-wrap justify-center gap-3"
            :data-testid="actionsTestId"
        >
            <slot name="actions" />
        </div>
    </Card>
</template>

<script setup>
import Card from './Card.vue';

defineProps({
    testId: {
        type: String,
        required: true,
    },
    actionsTestId: {
        type: String,
        default: undefined,
    },
});
</script>
