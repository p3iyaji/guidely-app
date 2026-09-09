<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        data-testid="modal"
    >
        <div
            class="absolute inset-0 bg-black/40"
            data-testid="modal-backdrop"
            @click="onBackdropClick"
        />
        <div
            v-bind="$attrs"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            class="relative z-10 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-surface shadow-[0_1px_3px_rgba(31,41,55,0.08)]"
            tabindex="-1"
        >
            <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
                <h2 :id="titleId" class="text-body font-semibold text-text">
                    {{ title }}
                </h2>
                <button
                    type="button"
                    class="rounded-md px-2 py-1 text-body text-text-muted hover:bg-surface-muted hover:text-text focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                    :disabled="closeDisabled"
                    data-testid="modal-close"
                    aria-label="Close"
                    @click="requestClose"
                >
                    Close
                </button>
            </div>
            <div class="overflow-y-auto px-6 py-4">
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, useId, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    closeDisabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const titleId = useId();

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document === 'undefined') {
            return;
        }

        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
    { immediate: true },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);

    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

function requestClose() {
    if (props.closeDisabled || !props.open) {
        return;
    }

    emit('close');
}

function onBackdropClick() {
    requestClose();
}

/**
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key === 'Escape') {
        requestClose();
    }
}
</script>
