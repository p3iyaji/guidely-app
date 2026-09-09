<template>
    <div class="mb-4">
        <label :for="id" class="sr-only">{{ label }}</label>
        <input
            :id="id"
            :value="modelValue"
            type="search"
            :placeholder="placeholder"
            :title="title"
            class="w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring"
            :data-testid="testId"
            @input="$emit('update:modelValue', $event.target.value)"
        >
    </div>
</template>

<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: 'Search',
    },
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search',
    },
    title: {
        type: String,
        default: '',
    },
    testId: {
        type: String,
        required: true,
    },
});

defineEmits(['update:modelValue']);
</script>
