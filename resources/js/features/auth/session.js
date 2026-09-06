import { computed, readonly, ref } from 'vue';
import { fetchMe, logout as apiLogout } from '../../api/auth';

/** @type {import('vue').Ref<object|null>} */
const user = ref(null);
const bootstrapped = ref(false);
const bootstrapping = ref(false);

/**
 * Shared session state for the SPA shell. Presentation only — menus never authorise APIs.
 */
export function useSession() {
    const isAuthenticated = computed(() => user.value !== null);
    const role = computed(() => user.value?.role ?? null);

    async function bootstrap() {
        if (bootstrapping.value) {
            return user.value;
        }

        bootstrapping.value = true;

        try {
            const result = await fetchMe();

            if (result.ok) {
                user.value = result.user;
            } else {
                user.value = null;
            }

            bootstrapped.value = true;

            return user.value;
        } finally {
            bootstrapping.value = false;
        }
    }

    async function clearSession() {
        user.value = null;
        bootstrapped.value = true;

        try {
            await apiLogout();
        } catch {
            // Local session already cleared; ignore logout API failures.
        }
    }

    function setUser(nextUser) {
        user.value = nextUser;
        bootstrapped.value = true;
    }

    return {
        user: readonly(user),
        role,
        isAuthenticated,
        bootstrapped: readonly(bootstrapped),
        bootstrapping: readonly(bootstrapping),
        bootstrap,
        clearSession,
        setUser,
    };
}
