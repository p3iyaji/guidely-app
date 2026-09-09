<template>
    <div data-testid="profile-page">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-heading font-semibold text-text">Profile</h1>
                <p class="mt-1 text-body text-text-muted">
                    Update your name, email, and password. Role and school assignment stay with Tenant Admins.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <ButtonSecondary
                    v-if="!loading && !loadError"
                    data-testid="profile-edit-open"
                    @click="openProfileForm"
                >
                    Edit profile
                </ButtonSecondary>
                <ButtonOutline
                    v-if="!loading && !loadError"
                    data-testid="profile-password-open"
                    @click="openPasswordForm"
                >
                    Change password
                </ButtonOutline>
            </div>
        </div>

        <div v-if="loading" class="mt-6 space-y-3" data-testid="profile-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <Card
            v-else-if="loadError"
            class="mt-6"
            data-testid="profile-error"
        >
            <p class="text-body text-danger" role="alert">{{ loadError }}</p>
        </Card>

        <Card
            v-else
            class="mt-6"
            data-testid="profile-details"
        >
            <dl class="space-y-3">
                <div>
                    <dt class="text-meta text-text-muted">Name</dt>
                    <dd class="text-body text-text" data-testid="profile-name">
                        {{ profileName }}
                    </dd>
                </div>
                <div>
                    <dt class="text-meta text-text-muted">Email</dt>
                    <dd class="text-body text-text" data-testid="profile-email">
                        {{ profileEmail }}
                    </dd>
                </div>
                <div>
                    <dt class="text-meta text-text-muted">Role</dt>
                    <dd class="text-body text-text" data-testid="profile-role">
                        {{ roleLabel(profileRole) }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Modal
            :open="formMode === 'profile'"
            title="Edit profile"
            :close-disabled="saving"
            data-testid="profile-form"
            @close="closeForm"
        >
            <form class="space-y-4" @submit.prevent="submitProfileForm">
                <div>
                    <label class="block text-body text-text" for="profile-name-input">Name</label>
                    <input
                        id="profile-name-input"
                        v-model="profileForm.name"
                        type="text"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="profile-name-input"
                    >
                    <p
                        v-if="fieldErrors.name"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-name"
                    >
                        {{ fieldErrors.name }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="profile-email-input">Email</label>
                    <input
                        id="profile-email-input"
                        v-model="profileForm.email"
                        type="email"
                        required
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="profile-email-input"
                    >
                    <p
                        v-if="fieldErrors.email"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-email"
                    >
                        {{ fieldErrors.email }}
                    </p>
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="profile-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="profile-submit"
                    >
                        {{ saving ? 'Saving…' : 'Save profile' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="profile-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>

        <Modal
            :open="formMode === 'password'"
            title="Change password"
            :close-disabled="saving"
            data-testid="profile-password-form"
            @close="closeForm"
        >
            <form class="space-y-4" @submit.prevent="submitPasswordForm">
                <div>
                    <label class="block text-body text-text" for="profile-current-password">Current password</label>
                    <input
                        id="profile-current-password"
                        v-model="passwordForm.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="profile-current-password-input"
                    >
                    <p
                        v-if="fieldErrors.current_password"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-current_password"
                    >
                        {{ fieldErrors.current_password }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="profile-new-password">New password</label>
                    <input
                        id="profile-new-password"
                        v-model="passwordForm.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="profile-password-input"
                    >
                    <p
                        v-if="fieldErrors.password"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password"
                    >
                        {{ fieldErrors.password }}
                    </p>
                </div>
                <div>
                    <label class="block text-body text-text" for="profile-password-confirmation">Confirm new password</label>
                    <input
                        id="profile-password-confirmation"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="profile-password-confirmation-input"
                    >
                    <p
                        v-if="fieldErrors.password_confirmation"
                        class="mt-1 text-meta text-danger"
                        data-testid="error-password_confirmation"
                    >
                        {{ fieldErrors.password_confirmation }}
                    </p>
                </div>
                <p
                    v-if="formError"
                    class="text-body text-danger"
                    data-testid="profile-password-form-error"
                    role="alert"
                >
                    {{ formError }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <ButtonPrimary
                        type="submit"
                        :disabled="saving"
                        data-testid="profile-password-submit"
                    >
                        {{ saving ? 'Saving…' : 'Save password' }}
                    </ButtonPrimary>
                    <ButtonSecondary
                        :disabled="saving"
                        data-testid="profile-password-cancel"
                        @click="closeForm"
                    >
                        Cancel
                    </ButtonSecondary>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import Modal from '../shared/ui/Modal.vue';

const ROLE_LABELS = {
    teacher: 'Teacher',
    support_staff: 'Support Staff',
    senco: 'SENCO',
    school_leader: 'School Leader',
    tenant_admin: 'Tenant Admin',
    trust_send_lead: 'Trust SEND Lead',
    trust_executive: 'Trust Executive',
    platform_operator: 'Platform Operator',
};

const session = useSession();
const loading = ref(true);
const loadError = ref('');
const profile = ref(null);
const formMode = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = reactive({
    name: '',
    email: '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const profileForm = reactive({
    name: '',
    email: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const profileName = computed(() => String(profile.value?.name ?? '—'));
const profileEmail = computed(() => String(profile.value?.email ?? '—'));
const profileRole = computed(() => profile.value?.role ?? session.role.value);

onMounted(async () => {
    await loadProfile();
});

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {string|null|undefined} role
 */
function roleLabel(role) {
    return ROLE_LABELS[role] ?? String(role ?? '—');
}

function clearFieldErrors() {
    fieldErrors.name = '';
    fieldErrors.email = '';
    fieldErrors.current_password = '';
    fieldErrors.password = '';
    fieldErrors.password_confirmation = '';
}

function resetPasswordForm() {
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
}

function closeForm() {
    formMode.value = null;
    formError.value = '';
    clearFieldErrors();
    resetPasswordForm();
}

function openProfileForm() {
    if (profile.value == null) {
        return;
    }

    clearFieldErrors();
    formError.value = '';
    profileForm.name = String(profile.value.name ?? '');
    profileForm.email = String(profile.value.email ?? '');
    formMode.value = 'profile';
}

function openPasswordForm() {
    clearFieldErrors();
    formError.value = '';
    resetPasswordForm();
    formMode.value = 'password';
}

/**
 * @param {Record<string, unknown>} payload
 */
function applyFieldErrors(payload) {
    const errors = payload.errors;

    if (!isRecord(errors)) {
        return;
    }

    for (const [field, messages] of Object.entries(errors)) {
        if (field in fieldErrors) {
            fieldErrors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
        }
    }
}

/**
 * @param {Record<string, unknown>} nextUser
 */
function applySavedUser(nextUser) {
    profile.value = nextUser;
    session.setUser({
        ...(session.user.value ?? {}),
        ...nextUser,
    });
}

async function loadProfile() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiFetch('/api/v1/me');

        if (!response.ok) {
            loadError.value = 'Unable to load Profile.';
            profile.value = isRecord(session.user.value) ? { ...session.user.value } : null;

            return;
        }

        const payload = await response.json();
        profile.value = isRecord(payload.data) ? payload.data : null;

        if (profile.value == null) {
            loadError.value = 'Unable to load Profile.';
        }
    } catch {
        loadError.value = 'Unable to load Profile.';
        profile.value = isRecord(session.user.value) ? { ...session.user.value } : null;
    } finally {
        loading.value = false;
    }
}

async function submitProfileForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    try {
        const response = await apiFetch('/api/v1/me', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: profileForm.name.trim(),
                email: profileForm.email.trim(),
            }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? 'Unable to update Profile.';

            return;
        }

        if (isRecord(payload.data)) {
            applySavedUser(payload.data);
        } else {
            await loadProfile();
        }

        closeForm();
    } catch {
        formError.value = 'Unable to update Profile.';
    } finally {
        saving.value = false;
    }
}

async function submitPasswordForm() {
    saving.value = true;
    formError.value = '';
    clearFieldErrors();

    if (passwordForm.password !== passwordForm.password_confirmation) {
        fieldErrors.password_confirmation = 'Password confirmation does not match.';
        saving.value = false;

        return;
    }

    try {
        const response = await apiFetch('/api/v1/me/password', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: passwordForm.current_password,
                password: passwordForm.password,
                password_confirmation: passwordForm.password_confirmation,
            }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                applyFieldErrors(payload);
            }

            formError.value = payload.message ?? 'Unable to update password.';

            return;
        }

        if (isRecord(payload.data)) {
            applySavedUser(payload.data);
        }

        closeForm();
    } catch {
        formError.value = 'Unable to update password.';
    } finally {
        saving.value = false;
    }
}
</script>
