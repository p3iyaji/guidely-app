<template>
    <a
        href="/"
        class="inline-flex items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-white/80"
        aria-label="GuidelyEdu"
    >
        <img
            src="/logo.png"
            alt=""
            :class="height"
            class="w-auto select-none rounded-md"
            draggable="false"
        />
    </a>
</template>

<script setup>
defineProps({
    height: {
        type: String,
        default: 'h-9',
    },
});
</script>
