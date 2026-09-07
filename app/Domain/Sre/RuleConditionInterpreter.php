<?php

namespace App\Domain\Sre;

/**
 * Interprets Pilot Rule condition JSON. Unsupported types make the Rule non-applicable.
 *
 * Deferred domains (`review_cycle_open`, `prior_determination`) are recorded as skipped
 * and make the Rule non-applicable until those domains exist.
 */
final class RuleConditionInterpreter
{
    /**
     * Condition types that are known but cannot be evaluated yet (deferred domains).
     *
     * @var list<string>
     */
    private const DEFERRED_TYPES = [
        'review_cycle_open',
        'prior_determination',
    ];

    /**
     * @var list<string>
     */
    private const SUPPORTED_TYPES = [
        'evidence_types_present',
        'threshold_code',
        'evidence_min_count',
        'provision_and_need_linked',
        'evidence_type_present',
        'prior_determination',
        'review_cycle_open',
    ];

    /**
     * @param  array<string, mixed>  $condition
     * @return array{
     *     applicable: bool,
     *     steps: list<array{type: string, status: string, detail: string, input?: array<string, mixed>}>
     * }
     */
    public function interpret(array $condition, EvaluationContext $context): array
    {
        $all = $condition['all'] ?? null;

        if (! is_array($all) || $all === []) {
            return [
                'applicable' => false,
                'steps' => [[
                    'type' => 'condition.all',
                    'status' => 'unsupported',
                    'detail' => 'Rule condition.all is missing or empty; Rule is non-applicable.',
                ]],
            ];
        }

        $steps = [];
        $applicable = true;

        foreach ($all as $index => $clause) {
            if (! is_array($clause)) {
                $steps[] = [
                    'type' => 'invalid_clause',
                    'status' => 'unsupported',
                    'detail' => "Condition clause at index {$index} is not an object; Rule is non-applicable.",
                ];
                $applicable = false;

                continue;
            }

            $type = is_string($clause['type'] ?? null) ? $clause['type'] : '';

            if ($type === '' || ! in_array($type, self::SUPPORTED_TYPES, true)) {
                $steps[] = [
                    'type' => $type !== '' ? $type : 'unknown',
                    'status' => 'unsupported',
                    'detail' => 'Unsupported condition type; Rule is non-applicable.',
                    'input' => $clause,
                ];
                $applicable = false;

                continue;
            }

            if (in_array($type, self::DEFERRED_TYPES, true)) {
                $steps[] = [
                    'type' => $type,
                    'status' => 'skipped',
                    'detail' => 'Deferred until domain exists; Rule is non-applicable.',
                    'input' => $clause,
                ];
                $applicable = false;

                continue;
            }

            $passed = $this->evaluateClause($type, $clause, $context);
            $steps[] = [
                'type' => $type,
                'status' => $passed ? 'passed' : 'failed',
                'detail' => $passed
                    ? 'Condition satisfied.'
                    : 'Condition not satisfied; Rule is skipped.',
                'input' => $clause,
            ];

            if (! $passed) {
                $applicable = false;
            }
        }

        return [
            'applicable' => $applicable,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $clause
     */
    private function evaluateClause(string $type, array $clause, EvaluationContext $context): bool
    {
        return match ($type) {
            'evidence_types_present' => $this->evidenceTypesPresent($clause, $context),
            'evidence_type_present' => $this->evidenceTypePresent($clause, $context),
            'threshold_code' => $this->thresholdCode($clause, $context),
            'evidence_min_count' => $this->evidenceMinCount($clause, $context),
            'provision_and_need_linked' => $context->hasProvisionAndNeedLinked(),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $clause
     */
    private function evidenceTypesPresent(array $clause, EvaluationContext $context): bool
    {
        $types = $clause['types'] ?? null;

        if (! is_array($types) || $types === []) {
            return false;
        }

        /** @var list<string> $typed */
        $typed = array_values(array_filter($types, is_string(...)));

        return $typed !== [] && $context->hasAllEvidenceTypes($typed);
    }

    /**
     * @param  array<string, mixed>  $clause
     */
    private function evidenceTypePresent(array $clause, EvaluationContext $context): bool
    {
        $evidenceType = $clause['evidence_type'] ?? null;

        if (! is_string($evidenceType) || $evidenceType === '') {
            return false;
        }

        return $context->hasEvidenceType($evidenceType);
    }

    /**
     * @param  array<string, mixed>  $clause
     */
    private function thresholdCode(array $clause, EvaluationContext $context): bool
    {
        $code = $clause['code'] ?? null;

        if (! is_string($code) || $code === '') {
            return false;
        }

        return $context->hasActiveThresholdCode($code);
    }

    /**
     * @param  array<string, mixed>  $clause
     */
    private function evidenceMinCount(array $clause, EvaluationContext $context): bool
    {
        $minCount = $this->nonNegativeInt($clause['min_count'] ?? null);

        if ($minCount === null) {
            return false;
        }

        return $context->evidence->count() >= $minCount;
    }

    /**
     * Accept only int ≥ 0 (or digit-only string). Negatives / floats / junk → null (failed).
     */
    private function nonNegativeInt(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
