<template>
    <div data-testid="evidence-base-page">
        <div v-if="loading" class="space-y-3" data-testid="evidence-base-loading">
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="line" />
            <LoadingSkeleton variant="card" />
        </div>

        <template v-else-if="!canViewEvidenceBase">
            <PageHero
                eyebrow="Pupils"
                :title="pupilName"
                :description="workingRecordDescription"
            >
                <template #action>
                    <RouterLink
                        to="/pupils"
                        class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-4 py-2 text-body font-semibold text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        data-testid="pupil-record-back"
                    >
                        Back to Pupils
                    </RouterLink>
                </template>
            </PageHero>

            <p
                v-if="loadError"
                class="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-body text-danger"
                data-testid="evidence-base-error"
                role="alert"
            >
                {{ loadError }}
            </p>

            <Card
                v-else
                data-testid="pupil-working-record"
            >
                <p
                    class="rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                    data-testid="pupil-record-note"
                    role="note"
                >
                    The Evidence Base is for SENCO and assigned teaching staff. You can manage this
                    working record, assignments, and import from Pupils.
                </p>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Year group</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-year">
                            {{ pupil?.year_group || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">SEND status</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-send-status">
                            {{ sendStatusLabel(pupil?.send_status) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Documentation</dt>
                        <dd class="mt-1">
                            <StatusPill :status="pupil?.documentation_status ?? 'not-started'" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Next review</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-next-review">
                            {{ formatWorkingRecordDate(pupil?.next_review_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Primary Need</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-primary-need">
                            {{ needLabel(pupil?.primary_need) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Secondary Need</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-secondary-need">
                            {{ needLabel(pupil?.secondary_need) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Assigned staff</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-assignees">
                            {{ assignedStaffLabel }}
                        </dd>
                    </div>
                    <div v-if="pupil?.notes" class="sm:col-span-2">
                        <dt class="text-meta font-semibold uppercase tracking-wider text-text-muted">Notes</dt>
                        <dd class="mt-1 text-body text-text" data-testid="pupil-record-notes">
                            {{ pupil.notes }}
                        </dd>
                    </div>
                </dl>
            </Card>
        </template>

        <template v-else>
            <div>
                <h1 class="text-heading font-semibold text-text" data-testid="evidence-base-title">
                    {{ pupilName }}
                </h1>
                <p
                    v-if="pupilSubtitle"
                    class="mt-1 text-body text-text-muted"
                    data-testid="evidence-base-subtitle"
                >
                    {{ pupilSubtitle }}
                </p>
            </div>

            <div
                class="mt-4 rounded-md bg-info-soft px-3 py-2 text-meta text-info"
                data-testid="evidence-base-disclaimer"
                role="note"
            >
                Documentation evaluations support professional judgement. They are not diagnoses,
                funding decisions, or statutory determinations.
            </div>

            <section
                v-if="canViewDeterminations"
                id="determinations-panel"
                class="mt-6"
                data-testid="evidence-determinations"
                aria-labelledby="evidence-determinations-heading"
            >
                <h2
                    id="evidence-determinations-heading"
                    class="text-body font-semibold text-text"
                >
                    Determinations
                </h2>
                <p class="mt-1 text-meta text-text-muted">
                    Current documentation evaluations for this Pupil. Expand a Determination to
                    review its Reasoning Pathway.
                </p>

                <p
                    v-if="determinationsError"
                    class="mt-3 text-body text-danger"
                    data-testid="evidence-determinations-error"
                    role="alert"
                >
                    {{ determinationsError }}
                </p>

                <p
                    v-else-if="determinationsLoading"
                    class="mt-3 text-meta text-text-muted"
                    data-testid="evidence-determinations-loading"
                >
                    Loading Determinations…
                </p>

                <p
                    v-else-if="determinations.length === 0"
                    class="mt-3 text-meta text-text-muted"
                    data-testid="evidence-determinations-empty"
                >
                    No current Determinations yet.
                </p>

                <ul
                    v-else
                    class="mt-3 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                    data-testid="evidence-determinations-list"
                >
                    <li
                        v-for="determination in determinations"
                        :key="determination.id"
                        class="px-4 py-3"
                        :data-testid="`determination-row-${determination.id}`"
                        :data-dimension="determination.dimension"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-body font-medium text-text" data-testid="determination-dimension">
                                    {{ determination.dimension ?? '—' }}
                                </p>
                                <p class="mt-1">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label font-medium"
                                        :class="determinationResultTone(determination.result)"
                                        data-testid="determination-result"
                                    >
                                        <span aria-hidden="true">●</span>
                                        {{ determination.result_label || humaniseDeterminationResult(determination.result) }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <ButtonOutline
                                    class="min-h-11"
                                    type="button"
                                    :data-testid="`determination-toggle-${determination.id}`"
                                    :aria-expanded="expandedDeterminationId === determination.id ? 'true' : 'false'"
                                    :aria-controls="`determination-pathway-${determination.id}`"
                                    @click="toggleDetermination(determination.id)"
                                >
                                    {{ expandedDeterminationId === determination.id ? 'Hide' : 'Show' }} pathway for
                                    {{ determination.dimension ?? 'this Determination' }}
                                </ButtonOutline>
                                <ButtonOutline
                                    v-if="canOverrideDetermination(determination) && overrideDeterminationId !== determination.id"
                                    class="min-h-11"
                                    type="button"
                                    data-testid="determination-override-open"
                                    @click="openOverridePanel(determination.id)"
                                >
                                    Override with rationale for
                                    {{ determination.dimension ?? 'this Determination' }}
                                </ButtonOutline>
                            </div>
                        </div>

                        <div
                            v-if="overrideDeterminationId === determination.id"
                            class="mt-4 space-y-4 rounded-lg border border-border bg-surface px-4 py-4"
                            data-testid="determination-override-panel"
                        >
                            <h3 class="text-body font-semibold text-text">Override with rationale</h3>
                            <p class="text-meta text-text-muted">
                                Record professional judgement for this dimension (at least 20 characters).
                                The engine result stays visible and is not changed.
                            </p>

                            <p
                                v-if="overrideError"
                                class="text-body text-danger"
                                data-testid="determination-override-error"
                                role="alert"
                            >
                                {{ overrideError }}
                            </p>

                            <form class="space-y-3" @submit.prevent="saveOverride(determination)">
                                <div>
                                    <label
                                        class="block text-body text-text"
                                        for="determination-override-rationale"
                                    >Rationale</label>
                                    <textarea
                                        id="determination-override-rationale"
                                        v-model="overrideForm.rationale"
                                        rows="4"
                                        required
                                        minlength="20"
                                        maxlength="5000"
                                        :aria-invalid="overrideFieldErrors.rationale ? 'true' : 'false'"
                                        aria-describedby="determination-override-rationale-hint"
                                        class="mt-1 w-full max-w-2xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                        data-testid="determination-override-rationale"
                                    />
                                    <p
                                        id="determination-override-rationale-hint"
                                        class="mt-1 text-meta text-text-muted"
                                    >
                                        Minimum 20 characters.
                                    </p>
                                    <p
                                        v-if="overrideFieldErrors.rationale"
                                        class="mt-1 text-body text-danger"
                                        data-testid="determination-override-rationale-error"
                                    >
                                        {{ overrideFieldErrors.rationale }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <ButtonPrimary
                                        class="min-h-11"
                                        :disabled="overrideSaving"
                                        data-testid="determination-override-save"
                                        type="submit"
                                    >
                                        {{ overrideSaving ? 'Saving…' : 'Save Override' }}
                                    </ButtonPrimary>
                                    <ButtonOutline
                                        class="min-h-11"
                                        :disabled="overrideSaving"
                                        data-testid="determination-override-cancel"
                                        type="button"
                                        @click="closeOverridePanel"
                                    >
                                        Cancel
                                    </ButtonOutline>
                                </div>
                            </form>
                        </div>

                        <div
                            v-if="expandedDeterminationId === determination.id"
                            :id="`determination-pathway-${determination.id}`"
                            class="mt-4"
                            data-testid="determination-pathway"
                        >
                            <ReasoningPathwayPanel
                                :pathway="determination.reasoning_pathway"
                                :result="determination.result"
                                :result-label="determination.result_label"
                                :rule="determination.rule"
                                :rule-library-label="determination.rule_library_version?.label ?? ''"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <p
                v-if="loadError"
                class="mt-4 text-body text-danger"
                data-testid="evidence-base-error"
                role="alert"
            >
                {{ loadError }}
            </p>

            <div
                class="mt-6 flex flex-wrap gap-2"
                role="group"
                aria-label="Evidence filters"
                data-testid="evidence-base-filters"
            >
                <button
                    v-for="chip in filterChips"
                    :key="chip.value"
                    type="button"
                    class="min-h-11 rounded-md border px-3 py-2 text-body focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :class="activeFilter === chip.value
                        ? 'border-border bg-surface-muted font-semibold text-text'
                        : 'border-border bg-surface text-text-muted'"
                    :aria-pressed="activeFilter === chip.value ? 'true' : 'false'"
                    :data-testid="`evidence-filter-${chip.value || 'all'}`"
                    @click="setFilter(chip.value)"
                >
                    {{ chip.label }}
                </button>
            </div>

            <div
                v-if="canAddReviewNote"
                class="mt-6"
                data-testid="evidence-review-note-section"
            >
                <ButtonOutline
                    v-if="!reviewNoteFormOpen"
                    class="min-h-11"
                    data-testid="evidence-review-note-open"
                    type="button"
                    @click="openReviewNoteForm"
                >
                    Add review note
                </ButtonOutline>

                <div
                    v-else
                    class="space-y-4 rounded-lg border border-border bg-surface px-4 py-4"
                    data-testid="evidence-review-note-panel"
                >
                    <h2 class="text-body font-semibold text-text">Add review note</h2>
                    <p class="text-meta text-text-muted">
                        Professional commentary for this Pupil’s Evidence Base. This is not classroom Observation.
                    </p>

                    <p
                        v-if="reviewNoteError"
                        class="text-body text-danger"
                        data-testid="evidence-review-note-error"
                        role="alert"
                    >
                        {{ reviewNoteError }}
                    </p>

                    <form class="space-y-3" @submit.prevent="saveReviewNote">
                        <div>
                            <label
                                class="block text-body text-text"
                                for="review-note-occurred-at"
                            >Date and time</label>
                            <input
                                id="review-note-occurred-at"
                                v-model="reviewNoteForm.occurred_at_local"
                                type="datetime-local"
                                required
                                class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                data-testid="evidence-review-note-occurred-at"
                            >
                            <p
                                v-if="reviewNoteFieldErrors.occurred_at"
                                class="mt-1 text-body text-danger"
                                data-testid="evidence-review-note-occurred-at-error"
                            >
                                {{ reviewNoteFieldErrors.occurred_at }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-body text-text"
                                for="review-note-body"
                            >Review commentary</label>
                                <textarea
                                    id="review-note-body"
                                    v-model="reviewNoteForm.body"
                                    rows="4"
                                    required
                                    maxlength="5000"
                                    class="mt-1 w-full max-w-2xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-review-note-body"
                                />
                            <p
                                v-if="reviewNoteFieldErrors.body"
                                class="mt-1 text-body text-danger"
                                data-testid="evidence-review-note-body-error"
                            >
                                {{ reviewNoteFieldErrors.body }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <ButtonPrimary
                                class="min-h-11"
                                :disabled="reviewNoteSaving"
                                data-testid="evidence-review-note-save"
                                type="submit"
                            >
                                {{ reviewNoteSaving ? 'Saving…' : 'Save review note' }}
                            </ButtonPrimary>
                            <ButtonOutline
                                class="min-h-11"
                                :disabled="reviewNoteSaving"
                                data-testid="evidence-review-note-cancel"
                                type="button"
                                @click="closeReviewNoteForm"
                            >
                                Cancel
                            </ButtonOutline>
                        </div>
                    </form>
                </div>
            </div>

            <Card
                v-if="!loadError && records.length === 0"
                class="mt-6"
                data-testid="evidence-base-empty"
            >
                <p class="text-body text-text" data-testid="evidence-base-empty-copy">
                    {{ emptyCopy }}
                </p>
                <div
                    v-if="showCaptureCta"
                    class="mt-4"
                >
                    <ButtonPrimary
                        class="min-h-11"
                        data-testid="evidence-base-capture-cta"
                        @click="goToCapture"
                    >
                        Capture Evidence
                    </ButtonPrimary>
                </div>
            </Card>

            <ul
                v-else-if="records.length > 0"
                class="mt-6 divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface"
                data-testid="evidence-base-list"
            >
                <li
                    v-for="record in records"
                    :key="record.id"
                    :id="`evidence-${record.id}`"
                    class="px-4 py-3"
                    data-testid="evidence-row"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-text" data-testid="evidence-type">
                                {{ typeLabel(record) }}
                            </p>
                            <p
                                v-if="record.author?.name"
                                class="text-meta text-text-muted"
                                data-testid="evidence-author"
                            >
                                {{ record.author.name }}
                            </p>
                            <div
                                v-if="provenanceLabel(record)"
                                class="mt-2 flex flex-wrap items-center gap-2"
                                data-testid="evidence-provenance"
                            >
                                <span
                                    class="inline-flex items-center rounded-full bg-surface-muted px-2.5 py-0.5 text-label font-medium text-text-muted"
                                    data-testid="evidence-source"
                                >
                                    {{ provenanceLabel(record) }}
                                </span>
                                <span
                                    v-if="record.external_id"
                                    class="break-all text-meta text-text-muted"
                                    data-testid="evidence-external-reference"
                                >
                                    External reference: {{ record.external_id }}
                                </span>
                            </div>
                            <p
                                v-if="termLabel(record)"
                                class="mt-1 text-meta text-text-muted"
                                data-testid="evidence-term"
                            >
                                {{ termLabel(record) }}
                            </p>
                            <p
                                v-if="record.body"
                                class="mt-2 text-body text-text"
                                data-testid="evidence-body"
                            >
                                {{ record.body }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                            <p class="text-meta text-text-muted" data-testid="evidence-occurred-at">
                                {{ formatOccurredAt(record.occurred_at) }}
                            </p>
                            <ButtonOutline
                                v-if="canAmend(record)"
                                class="min-h-11"
                                data-testid="evidence-amend-open"
                                @click="openAmend(record)"
                            >
                                Amend
                            </ButtonOutline>
                        </div>
                    </div>

                    <div
                        v-if="amendingId === record.id"
                        class="mt-4 space-y-4 border-t border-border pt-4"
                        data-testid="evidence-amend-panel"
                    >
                        <h2 class="text-body font-semibold text-text">Amend Evidence</h2>

                        <p
                            v-if="amendError"
                            class="text-body text-danger"
                            data-testid="evidence-amend-error"
                            role="alert"
                        >
                            {{ amendError }}
                        </p>

                        <form class="space-y-3" @submit.prevent="saveAmend(record)">
                            <div>
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-occurred-at-${record.id}`"
                                >Session date and time</label>
                                <input
                                    :id="`amend-occurred-at-${record.id}`"
                                    v-model="amendForm.occurred_at_local"
                                    type="datetime-local"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-occurred-at"
                                >
                                <p
                                    v-if="amendFieldErrors.occurred_at"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-occurred-at-error"
                                >
                                    {{ amendFieldErrors.occurred_at }}
                                </p>
                            </div>

                            <div v-if="record.type === 'observation'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-setting-${record.id}`"
                                >Setting</label>
                                <select
                                    :id="`amend-setting-${record.id}`"
                                    v-model="amendForm.setting_term_id"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-setting"
                                >
                                    <option disabled value="">Select a Setting</option>
                                    <option
                                        v-for="term in settingTerms"
                                        :key="term.id"
                                        :value="term.id"
                                    >
                                        {{ term.label }}
                                    </option>
                                </select>
                                <p
                                    v-if="amendFieldErrors.setting_term_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-setting-error"
                                >
                                    {{ amendFieldErrors.setting_term_id }}
                                </p>
                            </div>

                            <div v-if="record.type === 'intervention'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-provision-${record.id}`"
                                >Provision</label>
                                <select
                                    :id="`amend-provision-${record.id}`"
                                    v-model="amendForm.provision_term_id"
                                    required
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-provision"
                                >
                                    <option disabled value="">Select a Provision</option>
                                    <option
                                        v-for="term in provisionTerms"
                                        :key="term.id"
                                        :value="term.id"
                                    >
                                        {{ term.label }}
                                    </option>
                                </select>
                                <p
                                    v-if="amendFieldErrors.provision_term_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-provision-error"
                                >
                                    {{ amendFieldErrors.provision_term_id }}
                                </p>
                            </div>

                            <div v-if="record.type === 'response'">
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-related-intervention-${record.id}`"
                                >Related Intervention (optional)</label>
                                <select
                                    :id="`amend-related-intervention-${record.id}`"
                                    v-model="amendForm.related_intervention_id"
                                    class="mt-1 min-h-11 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-related-intervention"
                                >
                                    <option value="">None</option>
                                    <option
                                        v-for="item in relatedInterventions"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ interventionOptionLabel(item) }}
                                    </option>
                                </select>
                                <p
                                    v-if="amendFieldErrors.related_intervention_id"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-related-intervention-error"
                                >
                                    {{ amendFieldErrors.related_intervention_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="block text-body text-text"
                                    :for="`amend-body-${record.id}`"
                                >Notes</label>
                                <textarea
                                    :id="`amend-body-${record.id}`"
                                    v-model="amendForm.body"
                                    rows="4"
                                    :required="record.type !== 'intervention'"
                                    class="mt-1 w-full max-w-2xl rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:outline-none focus:ring-2 focus:ring-focus-ring"
                                    data-testid="evidence-amend-body"
                                />
                                <p
                                    v-if="amendFieldErrors.body"
                                    class="mt-1 text-body text-danger"
                                    data-testid="evidence-amend-body-error"
                                >
                                    {{ amendFieldErrors.body }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <ButtonPrimary
                                    class="min-h-11"
                                    :disabled="amendSaving"
                                    data-testid="evidence-amend-save"
                                    type="submit"
                                >
                                    {{ amendSaving ? 'Saving…' : 'Save amendment' }}
                                </ButtonPrimary>
                                <ButtonOutline
                                    class="min-h-11"
                                    :disabled="amendSaving"
                                    data-testid="evidence-amend-cancel"
                                    type="button"
                                    @click="closeAmend"
                                >
                                    Cancel
                                </ButtonOutline>
                            </div>
                        </form>

                        <div data-testid="evidence-versions-panel">
                            <h3 class="text-body font-semibold text-text">Previous versions</h3>
                            <p
                                v-if="versionsLoading"
                                class="mt-2 text-meta text-text-muted"
                                data-testid="evidence-versions-loading"
                            >
                                Loading previous versions…
                            </p>
                            <p
                                v-else-if="versionsError"
                                class="mt-2 text-body text-danger"
                                data-testid="evidence-versions-error"
                                role="alert"
                            >
                                {{ versionsError }}
                            </p>
                            <p
                                v-else-if="versions.length === 0"
                                class="mt-2 text-meta text-text-muted"
                                data-testid="evidence-versions-empty"
                            >
                                No previous versions yet.
                            </p>
                            <ul
                                v-else
                                class="mt-2 space-y-3"
                                data-testid="evidence-versions-list"
                            >
                                <li
                                    v-for="version in versions"
                                    :key="version.id"
                                    class="rounded-md border border-border px-3 py-2"
                                    data-testid="evidence-version-row"
                                >
                                    <p class="text-meta font-medium text-text">
                                        Version {{ version.version }}
                                        <span class="font-normal text-text-muted">
                                            · {{ formatOccurredAt(version.superseded_at) }}
                                            <template v-if="version.superseded_by?.name">
                                                · {{ version.superseded_by.name }}
                                            </template>
                                        </span>
                                    </p>
                                    <p
                                        v-if="version.snapshot?.body"
                                        class="mt-1 text-body text-text"
                                        data-testid="evidence-version-body"
                                    >
                                        {{ version.snapshot.body }}
                                    </p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiFetch } from '../api/client';
import { useSession } from '../features/auth/session';
import { canViewEvidenceBase as roleCanViewEvidenceBase } from '../features/shell/navByRole';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import Card from '../shared/ui/Card.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import PageHero from '../shared/ui/PageHero.vue';
import ReasoningPathwayPanel from '../shared/ui/ReasoningPathwayPanel.vue';
import StatusPill from '../shared/ui/StatusPill.vue';

const filterChips = [
    { value: '', label: 'All' },
    { value: 'observation', label: 'Observation' },
    { value: 'intervention', label: 'Intervention' },
    { value: 'response', label: 'Pupil Response' },
    { value: 'review_note', label: 'Review note' },
    { value: 'import', label: 'Import' },
];

const DEFAULT_DOCUMENT_TITLE = 'GuidelyEdu';

const session = useSession();
const route = useRoute();
const router = useRouter();

const pupil = ref(null);
const records = ref([]);
const loading = ref(true);
const loadError = ref('');
const activeFilter = ref('');
const hasAnySubmitted = ref(false);
let loadSeq = 0;

const amendingId = ref('');
const amendSaving = ref(false);
const amendError = ref('');
const amendForm = reactive({
    occurred_at_local: '',
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});
const amendFieldErrors = reactive({
    occurred_at: '',
    setting_term_id: '',
    provision_term_id: '',
    related_intervention_id: '',
    body: '',
});
const settingTerms = ref([]);
const provisionTerms = ref([]);
const relatedInterventions = ref([]);
const ontologyLoaded = ref(false);
const versions = ref([]);
const versionsLoading = ref(false);
const versionsError = ref('');

const reviewNoteFormOpen = ref(false);
const reviewNoteSaving = ref(false);
const reviewNoteError = ref('');
const reviewNoteForm = reactive({
    occurred_at_local: '',
    body: '',
});
const reviewNoteFieldErrors = reactive({
    occurred_at: '',
    body: '',
});

const OVERRIDE_ELIGIBLE_RESULTS = ['unmet', 'insufficient', 'escalated', 'review_required'];

const determinations = ref([]);
const determinationsLoading = ref(false);
const determinationsError = ref('');
const expandedDeterminationId = ref('');
const overrideDeterminationId = ref('');
const overrideSaving = ref(false);
const overrideError = ref('');
const overrideForm = reactive({
    rationale: '',
});
const overrideFieldErrors = reactive({
    rationale: '',
});

const isTeacher = computed(() => session.role.value === 'teacher');
const isSenco = computed(() => session.role.value === 'senco');
const isSchoolLeader = computed(() => session.role.value === 'school_leader');
const canViewEvidenceBase = computed(() => roleCanViewEvidenceBase(session.role.value));

const canViewDeterminations = computed(() => {
    return (isSenco.value || isSchoolLeader.value)
        && !loadError.value
        && pupil.value != null;
});

const canOverride = computed(() => {
    return (isSenco.value || isSchoolLeader.value)
        && !loadError.value
        && pupil.value != null;
});

const canAddReviewNote = computed(() => {
    return isSenco.value
        && !loadError.value
        && pupil.value != null;
});

const pupilName = computed(() => {
    if (!pupil.value) {
        return canViewEvidenceBase.value ? 'Evidence Base' : 'Pupil';
    }

    const name = `${pupil.value.given_name ?? ''} ${pupil.value.family_name ?? ''}`.trim();

    if (name !== '') {
        return name;
    }

    return canViewEvidenceBase.value ? 'Evidence Base' : 'Pupil';
});

const workingRecordDescription = computed(() => {
    if (loadError.value) {
        return 'This Pupil working record could not be loaded.';
    }

    return pupilSubtitle.value || 'Pupil working record for this Tenant.';
});

const assignedStaffLabel = computed(() => {
    const staff = Array.isArray(pupil.value?.assigned_staff) ? pupil.value.assigned_staff : [];
    const names = staff
        .map((row) => (row && typeof row === 'object' ? String(row.name ?? '') : ''))
        .filter(Boolean);

    return names.length > 0 ? names.join(', ') : '—';
});

/**
 * @param {unknown} status
 */
function sendStatusLabel(status) {
    if (status === 'sen_support') {
        return 'SEN Support';
    }

    if (status === 'ehcp') {
        return 'EHCP';
    }

    if (status === 'neither') {
        return 'Neither';
    }

    return '—';
}

/**
 * @param {unknown} need
 */
function needLabel(need) {
    if (need && typeof need === 'object' && 'label' in need && need.label) {
        return String(need.label);
    }

    return '—';
}

/**
 * @param {unknown} value
 */
function formatWorkingRecordDate(value) {
    if (value == null || value === '') {
        return '—';
    }

    const parsed = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleDateString('en-GB', {
        dateStyle: 'medium',
        timeZone: 'Europe/London',
    });
}

const pupilSubtitle = computed(() => {
    if (!pupil.value) {
        return '';
    }

    const parts = [];

    if (pupil.value.year_group) {
        parts.push(pupil.value.year_group);
    }

    if (pupil.value.send_status === 'sen_support') {
        parts.push('SEN Support');
    } else if (pupil.value.send_status === 'ehcp') {
        parts.push('EHCP');
    }

    const primaryNeed = pupil.value.primary_need;
    if (primaryNeed && typeof primaryNeed === 'object' && primaryNeed.label) {
        parts.push(primaryNeed.label);
    }

    const staff = Array.isArray(pupil.value.assigned_staff) ? pupil.value.assigned_staff : [];
    const names = staff
        .map((row) => (row && typeof row === 'object' ? row.name : ''))
        .filter(Boolean);
    if (names.length > 0) {
        parts.push(`Assigned: ${names.join(', ')}`);
    }

    return parts.join(' · ');
});

const emptyCopy = computed(() => {
    if (activeFilter.value && hasAnySubmitted.value) {
        return 'No Evidence Records match this filter.';
    }

    return 'No Evidence Records yet.';
});

const showCaptureCta = computed(() => {
    return isTeacher.value
        && !loadError.value
        && records.value.length === 0
        && !activeFilter.value
        && !hasAnySubmitted.value;
});

watch(
    pupilName,
    (title) => {
        document.title = title;

        if (route.name === 'pupil-detail') {
            route.meta.title = title;
        }
    },
    { immediate: true },
);

watch(
    () => route.params.id,
    async (id) => {
        if (!id) {
            pupil.value = null;
            records.value = [];
            hasAnySubmitted.value = false;
            loadError.value = canViewEvidenceBase.value
                ? 'Unable to load Evidence Base.'
                : 'Unable to load this Pupil.';
            loading.value = false;

            return;
        }

        await loadPage();
    },
);

watch(
    () => [route.query.focus, route.query.determination],
    () => {
        if (loading.value || determinations.value.length === 0) {
            return;
        }

        applyDeterminationFocus();
    },
);

onMounted(async () => {
    await loadPage();
});

onUnmounted(() => {
    document.title = DEFAULT_DOCUMENT_TITLE;
});

/**
 * @param {string} value
 */
async function setFilter(value) {
    if (activeFilter.value === value) {
        return;
    }

    activeFilter.value = value;
    closeAmend();
    closeReviewNoteForm();
    closeOverridePanel();
    await loadEvidence({ seq: loadSeq });
}

function goToCapture() {
    router.push({ name: 'capture' });
}

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isRecord(value) {
    return value != null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * @param {unknown} value
 */
function asArray(value) {
    return Array.isArray(value) ? value : [];
}

/**
 * @param {{ type?: string, lifecycle?: string, source?: string|null, author_id?: string, author?: { id?: string } }} record
 */
function canAmend(record) {
    const role = session.role.value;

    if (record.lifecycle !== 'submitted') {
        return false;
    }

    if (!['observation', 'intervention', 'response'].includes(record.type ?? '')) {
        return false;
    }

    if (role === 'senco') {
        return true;
    }

    if (role !== 'teacher' && role !== 'support_staff') {
        return false;
    }

    const authorId = record.author_id ?? record.author?.id;

    return authorId != null && String(authorId) === String(session.user.value?.id ?? '');
}

/**
 * @param {{ type?: string }} record
 */
function typeLabel(record) {
    return typeLabelFromType(record.type);
}

/**
 * @param {{ source?: string|null }} record
 */
function provenanceLabel(record) {
    const labels = {
        capture: 'Captured in GuidelyEdu',
        import: 'Imported',
        connector: 'Connector sync',
    };

    return labels[record.source] ?? '';
}

/**
 * @param {string|undefined} type
 */
function typeLabelFromType(type) {
    if (type === 'intervention') {
        return 'Intervention';
    }

    if (type === 'response') {
        return 'Pupil Response';
    }

    if (type === 'review_note') {
        return 'Review note';
    }

    if (type === 'observation') {
        return 'Observation';
    }

    return type ? String(type) : 'Unknown';
}

/**
 * @param {{ setting?: { label?: string }|null, provision?: { label?: string }|null, related_intervention?: { provision?: { label?: string }|null }|null }} record
 */
function termLabel(record) {
    if (record.setting?.label) {
        return record.setting.label;
    }

    if (record.provision?.label) {
        return record.provision.label;
    }

    if (record.related_intervention?.provision?.label) {
        return `Related: ${record.related_intervention.provision.label}`;
    }

    return '';
}

/**
 * @param {{ occurred_at?: string, provision?: { label?: string }|null }} item
 */
function interventionOptionLabel(item) {
    const when = formatOccurredAt(item.occurred_at);
    const provision = item.provision?.label ? ` · ${item.provision.label}` : '';

    return `${when}${provision}`;
}

/**
 * @param {string|undefined} iso
 */
function formatOccurredAt(iso) {
    if (!iso) {
        return '—';
    }

    const parsed = new Date(iso);

    if (Number.isNaN(parsed.getTime())) {
        return iso;
    }

    return parsed.toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/London',
    });
}

/**
 * @param {string|undefined} iso
 */
function toLocalDateTimeInput(iso) {
    if (!iso) {
        return '';
    }

    const parsed = new Date(iso);

    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const pad = (value) => String(value).padStart(2, '0');

    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
}

/**
 * @param {string} localValue
 */
function toUtcIso(localValue) {
    const parsed = new Date(localValue);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toISOString();
}

function clearAmendFieldErrors() {
    amendFieldErrors.occurred_at = '';
    amendFieldErrors.setting_term_id = '';
    amendFieldErrors.provision_term_id = '';
    amendFieldErrors.related_intervention_id = '';
    amendFieldErrors.body = '';
}

function clearReviewNoteFieldErrors() {
    reviewNoteFieldErrors.occurred_at = '';
    reviewNoteFieldErrors.body = '';
}

function closeReviewNoteForm() {
    reviewNoteFormOpen.value = false;
    reviewNoteSaving.value = false;
    reviewNoteError.value = '';
    reviewNoteForm.occurred_at_local = '';
    reviewNoteForm.body = '';
    clearReviewNoteFieldErrors();
}

function clearOverrideFieldErrors() {
    overrideFieldErrors.rationale = '';
}

function closeOverridePanel({ force = false } = {}) {
    if (overrideSaving.value && !force) {
        return;
    }

    overrideDeterminationId.value = '';
    overrideSaving.value = false;
    overrideError.value = '';
    overrideForm.rationale = '';
    clearOverrideFieldErrors();
}

/**
 * @param {string} determinationId
 */
function openOverridePanel(determinationId) {
    closeAmend();
    closeReviewNoteForm();
    overrideDeterminationId.value = determinationId;
    overrideSaving.value = false;
    overrideError.value = '';
    overrideForm.rationale = '';
    clearOverrideFieldErrors();
}

/**
 * @param {Record<string, unknown>} determination
 */
function canOverrideDetermination(determination) {
    if (!canOverride.value || !isRecord(determination)) {
        return false;
    }

    if (determination.is_current === false) {
        return false;
    }

    return OVERRIDE_ELIGIBLE_RESULTS.includes(String(determination.result ?? ''));
}

/**
 * @param {Record<string, unknown>} determination
 * @returns {Promise<void>}
 */
async function saveOverride(determination) {
    if (overrideSaving.value) {
        return;
    }

    overrideSaving.value = true;
    overrideError.value = '';
    clearOverrideFieldErrors();

    const rationale = overrideForm.rationale.trim();

    if (rationale.length < 20) {
        overrideFieldErrors.rationale = 'A rationale of at least 20 characters is required.';
        overrideSaving.value = false;

        return;
    }

    if (rationale.length > 5000) {
        overrideFieldErrors.rationale = 'A rationale may not be greater than 5000 characters.';
        overrideSaving.value = false;

        return;
    }

    try {
        const response = await apiFetch(`/api/v1/determinations/${determination.id}/overrides`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rationale }),
        });

        if (response.status === 422) {
            const payload = await response.json();
            const errors = isRecord(payload.errors) ? payload.errors : {};
            overrideFieldErrors.rationale = errors.rationale?.[0] ?? '';

            const unmapped = [
                errors.determination?.[0],
            ].filter((message) => typeof message === 'string' && message !== '');

            overrideError.value = unmapped.length > 0
                ? unmapped.join(' ')
                : 'Please correct the highlighted fields.';
            overrideSaving.value = false;

            return;
        }

        if (!response.ok) {
            overrideError.value = 'Unable to save this Override.';
            overrideSaving.value = false;

            return;
        }

        closeOverridePanel({ force: true });
        const refreshed = await loadDeterminations(loadSeq);

        if (!refreshed) {
            determinationsError.value = 'Override saved, but Determinations could not be refreshed.';
        }
    } catch {
        overrideError.value = 'Unable to save this Override.';
    } finally {
        overrideSaving.value = false;
    }
}

function openReviewNoteForm() {
    closeAmend();
    closeOverridePanel();
    reviewNoteFormOpen.value = true;
    reviewNoteError.value = '';
    clearReviewNoteFieldErrors();

    if (reviewNoteForm.occurred_at_local === '') {
        reviewNoteForm.occurred_at_local = toLocalDateTimeInput(new Date().toISOString());
    }
}

/**
 * @returns {Promise<void>}
 */
async function saveReviewNote() {
    if (reviewNoteSaving.value) {
        return;
    }

    reviewNoteSaving.value = true;
    reviewNoteError.value = '';
    clearReviewNoteFieldErrors();

    const pupilId = String(route.params.id ?? '');
    const occurredAt = toUtcIso(reviewNoteForm.occurred_at_local);
    const body = reviewNoteForm.body.trim();

    if (!occurredAt) {
        reviewNoteFieldErrors.occurred_at = 'Enter a valid date and time.';
        reviewNoteSaving.value = false;

        return;
    }

    if (body === '') {
        reviewNoteFieldErrors.body = 'Review note commentary is required.';
        reviewNoteSaving.value = false;

        return;
    }

    try {
        const response = await apiFetch('/api/v1/review-notes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pupil_id: pupilId,
                occurred_at: occurredAt,
                body,
            }),
        });

        if (response.status === 422) {
            const payload = await response.json();
            const errors = isRecord(payload.errors) ? payload.errors : {};
            reviewNoteFieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            reviewNoteFieldErrors.body = errors.body?.[0] ?? '';

            const unmapped = [
                errors.pupil_id?.[0],
                errors.client_type?.[0],
            ].filter((message) => typeof message === 'string' && message !== '');

            reviewNoteError.value = unmapped.length > 0
                ? unmapped.join(' ')
                : 'Please correct the highlighted fields.';
            reviewNoteSaving.value = false;

            return;
        }

        if (!response.ok) {
            reviewNoteError.value = 'Unable to save this review note.';
            reviewNoteSaving.value = false;

            return;
        }

        closeReviewNoteForm();
        hasAnySubmitted.value = true;
        activeFilter.value = '';

        const refreshed = await loadEvidence({ seq: loadSeq });

        if (!refreshed) {
            loadError.value = 'Review note saved, but the Evidence Base list could not be refreshed.';
        }
    } catch {
        reviewNoteError.value = 'Unable to save this review note.';
    } finally {
        reviewNoteSaving.value = false;
    }
}

function closeAmend() {
    amendingId.value = '';
    amendError.value = '';
    amendSaving.value = false;
    versions.value = [];
    versionsError.value = '';
    versionsLoading.value = false;
    relatedInterventions.value = [];
    clearAmendFieldErrors();
}

/**
 * @param {Record<string, unknown>} record
 */
async function openAmend(record) {
    if (amendingId.value === record.id) {
        closeAmend();

        return;
    }

    closeReviewNoteForm();
    closeOverridePanel();
    amendingId.value = String(record.id);
    amendError.value = '';
    clearAmendFieldErrors();
    amendForm.occurred_at_local = toLocalDateTimeInput(String(record.occurred_at ?? ''));
    amendForm.setting_term_id = record.setting?.id
        ? String(record.setting.id)
        : (record.setting_term_id ? String(record.setting_term_id) : '');
    amendForm.provision_term_id = record.provision?.id
        ? String(record.provision.id)
        : (record.provision_term_id ? String(record.provision_term_id) : '');
    amendForm.related_intervention_id = record.related_intervention_id
        ? String(record.related_intervention_id)
        : '';
    amendForm.body = record.body != null ? String(record.body) : '';

    await Promise.all([
        ensureOntologyTerms(),
        loadVersions(String(record.id)),
        record.type === 'response'
            ? loadRelatedInterventions(String(record.pupil_id ?? route.params.id ?? ''))
            : Promise.resolve(),
    ]);
}

async function ensureOntologyTerms() {
    if (ontologyLoaded.value) {
        return;
    }

    try {
        const [settingsResponse, provisionsResponse] = await Promise.all([
            apiFetch('/api/v1/ontology/setting-terms'),
            apiFetch('/api/v1/ontology/provision-terms'),
        ]);

        if (!settingsResponse.ok || !provisionsResponse.ok) {
            amendError.value = 'Unable to load Ontology terms for amending Evidence.';

            return;
        }

        const settingsPayload = await settingsResponse.json();
        const provisionsPayload = await provisionsResponse.json();
        settingTerms.value = asArray(settingsPayload.data).filter(isRecord);
        provisionTerms.value = asArray(provisionsPayload.data).filter(isRecord);
        ontologyLoaded.value = true;
    } catch {
        amendError.value = 'Unable to load Ontology terms for amending Evidence.';
    }
}

/**
 * @param {string} pupilId
 */
async function loadRelatedInterventions(pupilId) {
    relatedInterventions.value = [];

    if (pupilId === '') {
        amendError.value = 'Unable to load Interventions for this Pupil.';

        return;
    }

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/interventions`);

        if (!response.ok) {
            amendError.value = 'Unable to load Interventions for this Pupil.';

            return;
        }

        const payload = await response.json();
        relatedInterventions.value = asArray(payload.data).filter(isRecord);
    } catch {
        relatedInterventions.value = [];
        amendError.value = 'Unable to load Interventions for this Pupil.';
    }
}

/**
 * @param {string} evidenceId
 */
async function loadVersions(evidenceId) {
    versionsLoading.value = true;
    versionsError.value = '';
    versions.value = [];

    try {
        const response = await apiFetch(`/api/v1/evidence/${evidenceId}/versions`);

        if (amendingId.value !== evidenceId) {
            return;
        }

        if (!response.ok) {
            versionsError.value = 'Unable to load previous versions.';

            return;
        }

        const payload = await response.json();
        versions.value = asArray(payload.data).filter(isRecord);
    } catch {
        if (amendingId.value !== evidenceId) {
            return;
        }

        versionsError.value = 'Unable to load previous versions.';
    } finally {
        if (amendingId.value === evidenceId) {
            versionsLoading.value = false;
        }
    }
}

/**
 * @param {Record<string, unknown>} record
 */
async function saveAmend(record) {
    amendSaving.value = true;
    amendError.value = '';
    clearAmendFieldErrors();

    const occurredAt = toUtcIso(amendForm.occurred_at_local);

    if (!occurredAt) {
        amendFieldErrors.occurred_at = 'Enter a valid session date and time.';
        amendSaving.value = false;

        return;
    }

    /** @type {Record<string, unknown>} */
    const body = {
        occurred_at: occurredAt,
        body: amendForm.body.trim() === '' ? null : amendForm.body.trim(),
    };

    if (record.type === 'observation') {
        body.setting_term_id = amendForm.setting_term_id || null;
    } else if (record.type === 'intervention') {
        body.provision_term_id = amendForm.provision_term_id || null;
    } else if (record.type === 'response') {
        body.related_intervention_id = amendForm.related_intervention_id || null;
    }

    try {
        const response = await apiFetch(`/api/v1/evidence/${record.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        if (response.status === 422) {
            const payload = await response.json();
            const errors = isRecord(payload.errors) ? payload.errors : {};
            amendFieldErrors.occurred_at = errors.occurred_at?.[0] ?? '';
            amendFieldErrors.setting_term_id = errors.setting_term_id?.[0] ?? '';
            amendFieldErrors.provision_term_id = errors.provision_term_id?.[0] ?? '';
            amendFieldErrors.related_intervention_id = errors.related_intervention_id?.[0] ?? '';
            amendFieldErrors.body = errors.body?.[0] ?? '';
            amendError.value = 'Please correct the highlighted fields.';
            amendSaving.value = false;

            return;
        }

        if (!response.ok) {
            amendError.value = 'Unable to save this amendment.';
            amendSaving.value = false;

            return;
        }

        const payload = await response.json();
        const updated = isRecord(payload.data) ? payload.data : null;

        if (updated?.id) {
            records.value = records.value.map((row) => (
                row.id === updated.id ? { ...row, ...updated } : row
            ));
        }

        closeAmend();
        await loadEvidence({ seq: loadSeq });
    } catch {
        amendError.value = 'Unable to save this amendment.';
    } finally {
        amendSaving.value = false;
    }
}

async function loadPage() {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = '';
    activeFilter.value = '';
    hasAnySubmitted.value = false;
    pupil.value = null;
    records.value = [];
    determinations.value = [];
    determinationsError.value = '';
    expandedDeterminationId.value = '';
    closeAmend();
    closeReviewNoteForm();
    closeOverridePanel();

    try {
        const [pupilOk, evidenceOk] = await Promise.all([
            loadPupil(seq),
            canViewEvidenceBase.value
                ? loadEvidence({ trackUnfiltered: true, seq })
                : Promise.resolve(true),
        ]);

        if (seq !== loadSeq) {
            return;
        }

        if (!pupilOk || !evidenceOk) {
            records.value = [];
            loadError.value = canViewEvidenceBase.value
                ? 'Unable to load Evidence Base.'
                : 'Unable to load this Pupil.';
        } else if (canViewEvidenceBase.value && (isSenco.value || isSchoolLeader.value)) {
            await loadDeterminations(seq);
        }
    } catch {
        if (seq !== loadSeq) {
            return;
        }

        records.value = [];
        loadError.value = canViewEvidenceBase.value
            ? 'Unable to load Evidence Base.'
            : 'Unable to load this Pupil.';
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
            await nextTick();
            applyDeterminationFocus();
        }
    }
}

/**
 * @param {number} seq
 * @returns {Promise<boolean>}
 */
async function loadPupil(seq) {
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}`, {
            skipForbiddenRedirect: !canViewEvidenceBase.value,
        });

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            pupil.value = null;

            return false;
        }

        const payload = await response.json();
        pupil.value = isRecord(payload.data) ? payload.data : null;

        return pupil.value != null;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        pupil.value = null;

        return false;
    }
}

/**
 * @param {number} seq
 * @returns {Promise<boolean>}
 */
async function loadDeterminations(seq) {
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    determinationsLoading.value = true;
    determinationsError.value = '';

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/determinations?current=1`);

        if (seq !== loadSeq) {
            return false;
        }

        if (response.status === 403) {
            determinations.value = [];
            determinationsError.value = 'You don’t have access.';

            return false;
        }

        if (!response.ok) {
            determinations.value = [];
            determinationsError.value = 'Unable to load Determinations.';

            return false;
        }

        const payload = await response.json();

        if (seq !== loadSeq) {
            return false;
        }

        determinations.value = asArray(payload.data).filter(isRecord);

        return true;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        determinations.value = [];
        determinationsError.value = 'Unable to load Determinations.';

        return false;
    } finally {
        if (seq === loadSeq) {
            determinationsLoading.value = false;
        }
    }
}

/**
 * @param {string} determinationId
 */
function toggleDetermination(determinationId) {
    expandedDeterminationId.value = expandedDeterminationId.value === determinationId
        ? ''
        : determinationId;
}

function applyDeterminationFocus() {
    const focus = route.query.focus;
    const determinationId = typeof route.query.determination === 'string'
        ? route.query.determination
        : '';

    if (focus !== 'determination' && determinationId === '') {
        return;
    }

    if (determinations.value.length === 0) {
        return;
    }

    const target = determinationId !== ''
        ? determinations.value.find((row) => String(row.id) === determinationId)
        : determinations.value[0];

    if (target?.id) {
        expandedDeterminationId.value = String(target.id);
    }

    requestAnimationFrame(() => {
        document.getElementById('determinations-panel')?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    });
}

/**
 * @param {string|undefined} result
 */
function determinationResultTone(result) {
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
}

/**
 * @param {string|undefined} result
 */
function humaniseDeterminationResult(result) {
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
 * @param {{ trackUnfiltered?: boolean, seq?: number }} [options]
 * @returns {Promise<boolean>}
 */
async function loadEvidence(options = {}) {
    const { trackUnfiltered = false, seq = loadSeq } = options;
    const pupilId = String(route.params.id ?? '');

    if (pupilId === '') {
        return false;
    }

    const query = activeFilter.value ? `?filter=${encodeURIComponent(activeFilter.value)}` : '';

    try {
        const response = await apiFetch(`/api/v1/pupils/${pupilId}/evidence${query}`);

        if (seq !== loadSeq) {
            return false;
        }

        if (!response.ok) {
            records.value = [];

            if (!trackUnfiltered) {
                loadError.value = 'Unable to load Evidence Base.';
            }

            return false;
        }

        const payload = await response.json();
        records.value = asArray(payload.data).filter(isRecord);

        if (trackUnfiltered || !activeFilter.value) {
            hasAnySubmitted.value = records.value.length > 0;
        }

        if (!trackUnfiltered) {
            loadError.value = '';
        }

        return true;
    } catch {
        if (seq !== loadSeq) {
            return false;
        }

        records.value = [];

        if (!trackUnfiltered) {
            loadError.value = 'Unable to load Evidence Base.';
        }

        return false;
    }
}
</script>
