<template>
    <button
        type="button"
        class="inline-flex items-center rounded-md text-label font-semibold focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
            icon ? 'size-8 justify-center p-0' : 'min-h-9 px-2',
            tone === 'danger'
                ? 'text-danger hover:bg-danger-soft'
                : 'text-primary hover:bg-primary-soft',
        ]"
        :disabled="disabled"
        :aria-label="label || undefined"
        :title="label || undefined"
    >
        <AppIcon v-if="icon" :name="icon" class="size-4.5" />
        <span :class="icon ? 'sr-only' : ''"><slot>{{ label }}</slot></span>
    </button>
</template>

<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'primary',
    },
});
</script>
