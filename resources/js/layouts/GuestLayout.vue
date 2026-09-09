<template>
    <main class="min-h-screen lg:grid lg:grid-cols-[minmax(320px,0.85fr)_minmax(520px,1.15fr)]">
        <section class="relative hidden overflow-hidden bg-primary px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-x-0 top-0 h-1 bg-blue-300" />
            <BrandWordmark />

            <div class="relative max-w-md">
                <p class="mb-4 text-label font-semibold uppercase tracking-wider text-blue-100">
                    SEND operations
                </p>
                <h1 class="font-display text-[36px] font-bold leading-tight">
                    One calm place for the SEND work that matters.
                </h1>
                <p class="mt-5 max-w-sm text-body leading-6 text-white/75">
                    Secure access for teachers, SENCOs, school leaders, and trust teams.
                </p>
                <dl class="mt-10 grid grid-cols-2 gap-x-8 gap-y-6 border-t border-white/20 pt-6 text-body">
                    <div>
                        <dt class="text-white/60">Workspace</dt>
                        <dd class="mt-1 font-medium text-white">Role-aware access</dd>
                    </div>
                    <div>
                        <dt class="text-white/60">Data handling</dt>
                        <dd class="mt-1 font-medium text-white">Private by design</dd>
                    </div>
                </dl>
            </div>

            <p class="relative text-meta text-white/55">Authorised access only</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-canvas px-5 py-10 sm:px-10">
            <div class="w-full max-w-110">
                <div class="mb-10 inline-flex rounded-lg bg-white p-2 shadow-sm ring-1 ring-black/5 lg:hidden">
                    <BrandWordmark height="h-8" />
                </div>
                <RouterView />
            </div>
        </section>
    </main>
</template>

<script setup>
import { RouterView } from 'vue-router';
import BrandWordmark from '../shared/ui/BrandWordmark.vue';
</script>
