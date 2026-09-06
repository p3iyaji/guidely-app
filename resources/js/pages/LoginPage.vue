<template>
    <section class="mx-auto w-full max-w-md rounded-lg bg-surface border border-border p-6 shadow-[0_1px_3px_rgba(31,41,55,0.08)]">
        <h1 class="text-heading font-semibold text-text">Sign in</h1>
        <p class="mt-2 text-body text-text-muted">
            Use your GuidelyEdu staff credentials to access your Tenant workspace.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="onSubmit">
            <div>
                <label class="block text-label font-medium text-text" for="email">Email</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                    class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                >
            </div>

            <div>
                <label class="block text-label font-medium text-text" for="password">Password</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                >
            </div>

            <p
                v-if="errorMessage"
                class="rounded-md bg-danger-soft px-3 py-2 text-body text-danger"
                role="alert"
                data-testid="login-error"
            >
                {{ errorMessage }}
            </p>

            <button
                type="submit"
                class="w-full rounded-md bg-primary px-4 py-2 text-body font-medium text-primary-foreground hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
                :disabled="submitting"
            >
                {{ submitting ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
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
