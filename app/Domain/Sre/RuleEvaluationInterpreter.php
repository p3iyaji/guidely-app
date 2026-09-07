<?php

namespace App\Domain\Sre;

use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceType;

/**
 * Interprets Pilot Rule evaluation JSON against an Evidence snapshot.
 *
 * Unsupported evaluation types make the Rule non-applicable (caller skips).
 */
final class RuleEvaluationInterpreter
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_TYPES = [
        'chronological_sequence',
        'sufficiency_check',
        'proportionality_check',
        'outcome_marker_present',
        'escalation_sequence',
        'review_threshold_check',
    ];

    /**
     * @param  array<string, mixed>  $evaluation
     * @return array{
     *     applicable: bool,
     *     passed: bool,
     *     steps: list<array{type: string, status: string, detail: string, input?: array<string, mixed>}>
     * }
     */
    public function interpret(array $evaluation, EvaluationContext $context): array
    {
        $type = is_string($evaluation['type'] ?? null) ? $evaluation['type'] : '';

        if ($type === '' || ! in_array($type, self::SUPPORTED_TYPES, true)) {
            return [
                'applicable' => false,
                'passed' => false,
                'steps' => [[
                    'type' => $type !== '' ? $type : 'unknown',
                    'status' => 'unsupported',
                    'detail' => 'Unsupported evaluation type; Rule is non-applicable.',
                    'input' => $evaluation,
                ]],
            ];
        }

        $passed = match ($type) {
            'chronological_sequence' => $this->chronologicalSequence($evaluation, $context),
            'sufficiency_check' => $this->sufficiencyCheck($evaluation, $context),
            'proportionality_check' => $context->hasProvisionAndNeedLinked(),
            'outcome_marker_present' => $context->hasEvidenceType(EvidenceType::Response),
            'escalation_sequence' => $this->escalationSequence($evaluation, $context),
            'review_threshold_check' => $this->reviewThresholdCheck($evaluation, $context),
            default => false,
        };

        return [
            'applicable' => true,
            'passed' => $passed,
            'steps' => [[
                'type' => $type,
                'status' => $passed ? 'passed' : 'failed',
                'detail' => $passed ? 'Evaluation satisfied.' : 'Evaluation not satisfied.',
                'input' => $evaluation,
            ]],
        ];
    }

    /**
     * First occurrence of each required type must appear in the given order.
     * Duplicate entries in requires_order fail (one occurrence cannot satisfy two slots).
     *
     * @param  array<string, mixed>  $evaluation
     */
    private function chronologicalSequence(array $evaluation, EvaluationContext $context): bool
    {
        $order = $evaluation['requires_order'] ?? null;

        if (! is_array($order) || $order === []) {
            return false;
        }

        /** @var list<string> $typedOrder */
        $typedOrder = array_values(array_filter($order, is_string(...)));

        if ($typedOrder === []) {
            return false;
        }

        if (count($typedOrder) !== count(array_unique($typedOrder))) {
            return false;
        }

        if (! $context->hasAllEvidenceTypes($typedOrder)) {
            return false;
        }

        $firstIndex = [];

        foreach ($context->evidence->values() as $index => $record) {
            /** @var EvidenceRecord $record */
            $value = $record->type->value;

            if (! in_array($value, $typedOrder, true)) {
                continue;
            }

            if (! array_key_exists($value, $firstIndex)) {
                $firstIndex[$value] = $index;
            }
        }

        $previous = null;

        foreach ($typedOrder as $type) {
            $index = $firstIndex[$type] ?? null;

            if ($index === null) {
                return false;
            }

            if ($previous !== null && $index <= $previous) {
                return false;
            }

            $previous = $index;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function sufficiencyCheck(array $evaluation, EvaluationContext $context): bool
    {
        $minDistinct = $this->nonNegativeInt($evaluation['min_distinct_types'] ?? null);

        if ($minDistinct === null) {
            return false;
        }

        return count($context->evidenceTypeValues()) >= $minDistinct;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function escalationSequence(array $evaluation, EvaluationContext $context): bool
    {
        $requires = $evaluation['requires'] ?? null;

        if (! is_array($requires) || $requires === []) {
            return false;
        }

        /** @var list<string> $typed */
        $typed = array_values(array_filter($requires, is_string(...)));

        return $typed !== [] && $context->hasAllEvidenceTypes($typed);
    }

    /**
     * Without Review Cycle timestamps, treat as a minimum Evidence count gate.
     *
     * @param  array<string, mixed>  $evaluation
     */
    private function reviewThresholdCheck(array $evaluation, EvaluationContext $context): bool
    {
        $min = $this->nonNegativeInt($evaluation['min_evidence_since_last_review'] ?? null);

        if ($min === null) {
            return false;
        }

        return $context->evidence->count() >= $min;
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
