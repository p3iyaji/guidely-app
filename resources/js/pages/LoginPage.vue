<template>
    <section class="w-full">
        <header class="mb-8">
            <p class="mb-2 text-label font-semibold uppercase tracking-wider text-primary">
                Secure workspace
            </p>
            <h1 class="font-display text-display font-bold text-text">Welcome back</h1>
            <p class="mt-2 text-body leading-6 text-text-muted">
                Sign in with your GuidelyEdu staff account to continue.
            </p>
        </header>

        <form class="space-y-5" @submit.prevent="onSubmit">
            <div>
                <label class="mb-2 block text-body font-medium text-text" for="email">
                    Email address
                </label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                    autofocus
                    placeholder="name@school.org.uk"
                    class="min-h-12 w-full rounded-md border border-border bg-surface px-4 text-body text-text placeholder:text-text-muted/70 hover:border-border-strong focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring"
                >
                <p class="mt-2 text-meta text-text-muted">
                    Use the email assigned to your school or trust.
                </p>
            </div>

            <div>
                <label class="mb-2 block text-body font-medium text-text" for="password">
                    Password
                </label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="min-h-12 w-full rounded-md border border-border bg-surface px-4 text-body text-text hover:border-border-strong focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring"
                >
            </div>

            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-body text-text">
                <input
                    type="checkbox"
                    class="size-4 rounded border-border-strong accent-primary focus:ring-focus-ring"
                >
                Keep me signed in on this device
            </label>

            <p
                v-if="errorMessage"
                class="rounded-md border border-danger/25 bg-danger-soft px-4 py-3 text-body text-danger"
                role="alert"
                data-testid="login-error"
            >
                <span class="block font-semibold">We could not sign you in</span>
                <span class="mt-1 block">{{ errorMessage }}</span>
            </p>

            <button
                type="submit"
                class="flex min-h-12 w-full items-center justify-center rounded-md bg-primary px-5 text-body font-semibold text-primary-foreground transition-colors hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="submitting"
            >
                {{ submitting ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>

        <p class="mt-8 border-t border-border pt-6 text-center text-body text-text-muted">
            Need access or help signing in?
            <span class="font-medium text-text">Contact your administrator.</span>
        </p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api/auth';
import { useSession } from '../features/auth/session';

const router = useRouter();

const email = ref('');
const password = ref('');
const errorMessage = ref('');
const submitting = ref(false);

async function onSubmit() {
    errorMessage.value = '';
    submitting.value = true;

    try {
        const result = await login(email.value, password.value);

        if (!result.ok) {
            errorMessage.value = result.message || 'These credentials do not match our records.';

            return;
        }

        await useSession().bootstrap();
        await router.push({ name: 'home' });
    } catch (error) {
        errorMessage.value = error instanceof Error && error.message.includes('CSRF')
            ? error.message
            : 'These credentials do not match our records.';
    } finally {
        submitting.value = false;
    }
}
</script>
