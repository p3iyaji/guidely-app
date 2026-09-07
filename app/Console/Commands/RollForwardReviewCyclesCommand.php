<?php

namespace App\Console\Commands;

use App\Domain\Reviews\RollForwardReviewCycles;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class RollForwardReviewCyclesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'guidely:roll-forward-review-cycles
                            {--tenant= : Optional Tenant ULID to limit the run}';

    /**
     * @var string
     */
    protected $description = 'Create the next Annual Review for Pupils whose latest closed Annual Review anniversary is due.';

    public function handle(FeatureFlagResolver $flags, RollForwardReviewCycles $rollForward): int
    {
        $tenantId = trim((string) ($this->option('tenant') ?? ''));
        $query = Tenant::query()->orderBy('id');

        if ($tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get();

        if ($tenantId !== '' && $tenants->isEmpty()) {
            $this->error("Tenant [{$tenantId}] was not found.");

            return self::FAILURE;
        }

        $cliRequest = Request::create('/', 'CONSOLE');
        $createdCount = 0;
        $skippedCount = 0;
        $failed = false;

        foreach ($tenants as $tenant) {
            if (! $flags->isEnabled(FeatureFlagKey::ReviewCycleAutomation, $tenant)) {
                $skippedCount++;

                continue;
            }

            try {
                $created = $rollForward->handle(
                    $tenant,
                    $cliRequest,
                    'guidely:roll-forward-review-cycles',
                );
                $createdCount += $created->count();
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error("Roll-forward failed for Tenant [{$tenant->id}]: {$exception->getMessage()}");
            }
        }

        $this->info("Created {$createdCount} Review Cycle(s). Skipped {$skippedCount} Tenant(s) with automation disabled.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
