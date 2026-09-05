<template>
    <div class="min-h-screen flex flex-col">
        <header
            class="flex items-center px-[var(--spacing-page)] shrink-0"
            style="height: var(--spacing-topbar-height); background-color: var(--color-topbar)"
        >
            <BrandWordmark />
        </header>
        <div class="flex flex-1 min-h-0">
            <aside
                class="hidden md:block shrink-0 border-r border-border bg-sidebar"
                style="width: var(--spacing-sidebar-width)"
            />
            <main class="flex-1" style="padding: var(--spacing-page)">
                <RouterView />
            </main>
        </div>
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
import BrandWordmark from './shared/ui/BrandWordmark.vue';
</script>
