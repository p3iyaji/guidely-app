import { computed, readonly, ref } from 'vue';
import { fetchMe, logout as apiLogout } from '../../api/auth';
import { HYBRID_TOKEN_STORAGE_KEY } from '../evidence/clientType';

/** @type {import('vue').Ref<object|null>} */
const user = ref(null);
const bootstrapped = ref(false);
const bootstrapping = ref(false);

function clearLocalAuthArtifacts() {
    user.value = null;
    bootstrapped.value = true;

    if (typeof sessionStorage !== 'undefined') {
        sessionStorage.removeItem(HYBRID_TOKEN_STORAGE_KEY);
    }
}

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
        try {
            await apiLogout();
        } catch {
            // Always clear local session so the shell returns to login.
        } finally {
            clearLocalAuthArtifacts();
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
