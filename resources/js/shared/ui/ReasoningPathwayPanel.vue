<template>
    <div
        class="rounded-md border border-border bg-surface-muted/40 px-3 py-3"
        data-testid="reasoning-pathway-panel"
    >
        <h3 class="text-body font-semibold text-text">Reasoning Pathway</h3>

        <div class="mt-3 space-y-3" data-testid="reasoning-pathway-body">
            <section data-testid="pathway-rule">
                <h4 class="text-meta font-semibold text-text">Rule</h4>
                <p class="mt-1 text-body text-text">
                    <template v-if="ruleCode || ruleLabel">
                        <span data-testid="pathway-rule-code">{{ ruleCode || '—' }}</span>
                        <span v-if="ruleLabel"> — {{ ruleLabel }}</span>
                    </template>
                    <template v-else>
                        <span data-testid="pathway-rule-none">No applicable Rule</span>
                    </template>
                </p>
                <p
                    v-if="ruleLibraryLabel"
                    class="mt-1 text-meta text-text-muted"
                    data-testid="pathway-rule-library"
                >
                    Rule Library: {{ ruleLibraryLabel }}
                </p>
            </section>

            <section v-if="conditionSteps.length > 0" data-testid="pathway-conditions">
                <h4 class="text-meta font-semibold text-text">Conditions</h4>
                <ol class="mt-1 list-decimal space-y-1 pl-5 text-body text-text">
                    <li
                        v-for="(step, index) in conditionSteps"
                        :key="`condition-${index}`"
                        data-testid="pathway-condition-step"
                    >
                        {{ formatStep(step) }}
                    </li>
                </ol>
            </section>

            <section v-if="evaluationSteps.length > 0" data-testid="pathway-evaluations">
                <h4 class="text-meta font-semibold text-text">Evaluation</h4>
                <ol class="mt-1 list-decimal space-y-1 pl-5 text-body text-text">
                    <li
                        v-for="(step, index) in evaluationSteps"
                        :key="`evaluation-${index}`"
                        data-testid="pathway-evaluation-step"
                    >
                        {{ formatStep(step) }}
                    </li>
                </ol>
            </section>

            <section data-testid="pathway-evidence">
                <h4 class="text-meta font-semibold text-text">Evidence Records</h4>
                <ul
                    v-if="evidenceIds.length > 0"
                    class="mt-1 space-y-1 text-body text-text"
                >
                    <li
                        v-for="evidenceId in evidenceIds"
                        :key="evidenceId"
                    >
                        <a
                            class="text-info underline underline-offset-2 hover:text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            :href="`#evidence-${evidenceId}`"
                            data-testid="pathway-evidence-link"
                        >
                            Evidence {{ shortId(evidenceId) }}
                        </a>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-1 text-meta text-text-muted"
                    data-testid="pathway-evidence-empty"
                >
                    No Evidence Records cited.
                </p>
            </section>

            <section data-testid="pathway-result">
                <h4 class="text-meta font-semibold text-text">Determination</h4>
                <p class="mt-1">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label font-medium"
                        :class="resultToneClass"
                        data-testid="pathway-result-pill"
                    >
                        <span aria-hidden="true">●</span>
                        {{ resultLabel }}
                    </span>
                </p>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    pathway: {
        type: Object,
        default: null,
    },
    result: {
        type: String,
        default: '',
    },
    resultLabel: {
        type: String,
        default: '',
    },
    ruleLibraryLabel: {
        type: String,
        default: '',
    },
    rule: {
        type: Object,
        default: null,
    },
});

const pathwayRecord = computed(() => (
    props.pathway != null && typeof props.pathway === 'object' && !Array.isArray(props.pathway)
        ? props.pathway
        : {}
));

const ruleFromPathway = computed(() => {
    const rule = pathwayRecord.value.rule;

    return rule != null && typeof rule === 'object' ? rule : null;
});

const ruleCode = computed(() => {
    if (props.rule?.code) {
        return String(props.rule.code);
    }

    if (ruleFromPathway.value?.code) {
        return String(ruleFromPathway.value.code);
    }

    return '';
});

const ruleLabel = computed(() => {
    if (props.rule?.label) {
        return String(props.rule.label);
    }

    if (ruleFromPathway.value?.label) {
        return String(ruleFromPathway.value.label);
    }

    return '';
});

const conditionSteps = computed(() => asStepList(pathwayRecord.value.condition_steps));
const evaluationSteps = computed(() => asStepList(pathwayRecord.value.evaluation_steps));

const evidenceIds = computed(() => {
    const ids = pathwayRecord.value.evidence_ids;

    return Array.isArray(ids)
        ? ids.filter((id) => typeof id === 'string' && id !== '')
        : [];
});

const resolvedResult = computed(() => {
    if (props.result) {
        return props.result;
    }

    return pathwayRecord.value.result ? String(pathwayRecord.value.result) : '';
});

const resultLabel = computed(() => {
    if (props.resultLabel) {
        return props.resultLabel;
    }

    return humaniseResult(resolvedResult.value);
});

const resultToneClass = computed(() => {
    const result = resolvedResult.value;

    if (result === 'met') {
        return 'bg-success-soft text-success';
    }

    if (result === 'uncovered') {
        return 'bg-danger-soft text-danger';
    }

    if (result === 'escalated' || result === 'review_required' || result === 'unmet') {
        return 'bg-warning-soft text-warning';
    }

    return 'bg-surface-muted text-text-muted';
});

/**
 * @param {unknown} value
 * @returns {Record<string, unknown>[]}
 */
function asStepList(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter((step) => step != null && typeof step === 'object' && !Array.isArray(step));
}

/**
 * @param {Record<string, unknown>} step
 */
function formatStep(step) {
    const detail = typeof step.detail === 'string' ? step.detail : '';
    const type = typeof step.type === 'string' ? step.type : '';
    const status = typeof step.status === 'string' ? step.status : '';

    if (detail !== '') {
        return status !== '' ? `${detail} (${status})` : detail;
    }

    if (type !== '') {
        return status !== '' ? `${type}: ${status}` : type;
    }

    return status !== '' ? status : 'Step';
}

/**
 * @param {string} result
 */
function humaniseResult(result) {
    const labels = {
        met: 'Met',
        unmet: 'Not met',
        insufficient: 'Insufficient',
        uncovered: 'Uncovered',
        escalated: 'Escalated',
        review_required: 'Review required',
    };

    return labels[result] ?? (result || '—');
}

/**
 * @param {string} id
 */
function shortId(id) {
    if (id.length <= 10) {
        return id;
    }

    return `${id.slice(0, 8)}…`;
}
</script>
