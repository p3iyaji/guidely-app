<template>
    <div data-testid="import-page">
        <div>
            <h1 class="text-heading font-semibold text-text">Import</h1>
            <p class="mt-1 text-body text-text-muted">
                Download the Import Template, upload a Pupil CSV, and review committed rows versus errors by row number.
            </p>
        </div>

        <Card class="mt-6" data-testid="import-download-card">
            <h2 class="text-body font-semibold text-text">Import Template</h2>
            <p class="mt-1 text-body text-text-muted">
                CSV headers for Pupils. Evidence columns are not supported yet and will return row errors.
            </p>
            <p
                v-if="templateError"
                class="mt-2 text-body text-danger"
                data-testid="import-template-error"
                role="alert"
            >
                {{ templateError }}
            </p>
            <div class="mt-4">
                <ButtonSecondary
                    :disabled="downloadingTemplate"
                    data-testid="import-download-template"
                    @click="downloadTemplate"
                >
                    {{ downloadingTemplate ? 'Downloading…' : 'Download Import Template' }}
                </ButtonSecondary>
            </div>
        </Card>

        <Card class="mt-6" data-testid="import-upload-card">
            <h2 class="text-body font-semibold text-text">Upload Pupils</h2>
            <p class="mt-1 text-body text-text-muted">
                Valid rows commit; invalid rows report by number without failing the whole file.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="uploadFile">
                <div>
                    <label class="block text-body text-text" for="import-file">CSV file</label>
                    <input
                        id="import-file"
                        ref="fileInput"
                        type="file"
                        accept=".csv,text/csv"
                        class="mt-1 block w-full max-w-md text-body text-text file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-body file:font-medium file:text-primary-foreground"
                        data-testid="import-file-input"
                        @change="onFileChange"
                    >
                </div>
                <p
                    v-if="uploadError"
                    class="text-body text-danger"
                    data-testid="import-upload-error"
                    role="alert"
                >
                    {{ uploadError }}
                </p>
                <ButtonPrimary
                    type="submit"
                    :disabled="uploading || !selectedFile"
                    data-testid="import-upload-submit"
                >
                    {{ uploading ? 'Uploading…' : 'Upload CSV' }}
                </ButtonPrimary>
            </form>
        </Card>

        <Card
            v-if="results"
            class="mt-6"
            data-testid="import-results"
        >
            <h2 class="text-body font-semibold text-text">Results</h2>
            <p class="mt-1 text-body text-text-muted" data-testid="import-results-summary">
                Committed {{ results.summary.committed_count }};
                errors {{ results.summary.error_count }}.
            </p>

            <div v-if="results.committed.length > 0" class="mt-4" data-testid="import-committed">
                <h3 class="text-body font-semibold text-text">Committed</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-body">
                        <thead class="bg-surface">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Row</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Action</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Name</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">MIS key</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="item in results.committed"
                                :key="`${item.row}-${item.id}`"
                                data-testid="import-committed-row"
                            >
                                <td class="px-3 py-2 text-text">{{ item.row }}</td>
                                <td class="px-3 py-2 text-text">{{ item.action }}</td>
                                <td class="px-3 py-2 text-text">{{ item.given_name }} {{ item.family_name }}</td>
                                <td class="px-3 py-2 text-text">{{ item.mis_key ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="results.errors.length > 0" class="mt-4" data-testid="import-errors">
                <h3 class="text-body font-semibold text-text">Errors</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-body">
                        <thead class="bg-surface">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Row</th>
                                <th scope="col" class="px-3 py-2 font-medium text-text">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="item in results.errors"
                                :key="`error-${item.row}-${item.message}`"
                                data-testid="import-error-row"
                            >
                                <td class="px-3 py-2 text-text">{{ item.row }}</td>
                                <td class="px-3 py-2 text-danger">{{ item.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Card>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { apiFetch } from '../api/client';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';

const downloadingTemplate = ref(false);
const templateError = ref('');
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const results = ref(null);
const fileInput = ref(null);

/**
 * @param {Event} event
 */
function onFileChange(event) {
    const input = event.target;
    selectedFile.value = input.files?.[0] ?? null;
    uploadError.value = '';
}

async function downloadTemplate() {
    downloadingTemplate.value = true;
    templateError.value = '';

    try {
        const response = await apiFetch('/api/v1/import/template');
        if (!response.ok) {
            templateError.value = 'Unable to download Import Template.';
            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'guidely-import-template.csv';
        link.click();
        URL.revokeObjectURL(url);
    } catch {
        templateError.value = 'Unable to download Import Template.';
    } finally {
        downloadingTemplate.value = false;
    }
}

async function uploadFile() {
    if (!selectedFile.value) {
        uploadError.value = 'Choose a CSV file to upload.';
        return;
    }

    uploading.value = true;
    uploadError.value = '';
    results.value = null;

    try {
        const body = new FormData();
        body.append('file', selectedFile.value);

        const response = await apiFetch('/api/v1/import/pupils', {
            method: 'POST',
            body,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            uploadError.value = payload.message
                ?? payload.errors?.file?.[0]
                ?? 'Unable to upload Import CSV.';
            return;
        }

        results.value = payload.data ?? null;
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    } catch {
        uploadError.value = 'Unable to upload Import CSV.';
    } finally {
        uploading.value = false;
    }
}
</script>
