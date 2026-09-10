<?php

namespace App\Domain\Reporting;

final readonly class DashboardSummary
{
    /**
     * @param  list<array{
     *     type: 'review_cycle'|'gap'|'draft',
     *     id: string,
     *     title: string,
     *     detail: string,
     *     href: string,
     *     priority: 'overdue'|'due'|'gap'|'draft',
     *     date: ?string
     * }>  $actionItems
     */
    public function __construct(
        public ?int $pupilsInScope,
        public ?int $openGaps,
        public ?int $reviewCyclesDue,
        public ?int $drafts,
        public int $windowDays,
        public array $actionItems,
    ) {}
}
