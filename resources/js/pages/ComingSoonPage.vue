<template>
    <Card data-testid="coming-soon">
        <h1 class="text-heading font-semibold text-text">{{ title }}</h1>
        <p class="mt-2 text-body text-text-muted">
            Coming soon. This surface is not available yet.
        </p>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Card from '../shared/ui/Card.vue';

const route = useRoute();

const title = computed(() => route.meta.title ?? 'Coming soon');
</script>
