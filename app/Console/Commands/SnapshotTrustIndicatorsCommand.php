<?php

namespace App\Console\Commands;

use App\Domain\Reporting\SnapshotTrustIndicators;
use App\Domain\Tenancy\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('guidely:snapshot-trust-indicators {--tenant= : Optional Tenant ULID to limit the run} {--month= : Calendar month Y-m in Europe/London}')]
#[Description('Store monthly Trust Indicator aggregates for portfolio trends.')]
class SnapshotTrustIndicatorsCommand extends Command
{
    public function handle(SnapshotTrustIndicators $snapshot): int
    {
        $tenantId = trim((string) ($this->option('tenant') ?? ''));
        $month = trim((string) ($this->option('month') ?? ''));

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $this->error('Month must be Y-m (Europe/London calendar month).');

            return self::FAILURE;
        }

        $month = $month === '' ? null : $month;
        $query = Tenant::query()->orderBy('id');

        if ($tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get();

        if ($tenantId !== '' && $tenants->isEmpty()) {
            $this->error("Tenant [{$tenantId}] was not found.");

            return self::FAILURE;
        }

        $written = 0;
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $written += $snapshot->handle($tenant, $month);
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error("Snapshot failed for Tenant [{$tenant->id}]: {$exception->getMessage()}");
            }
        }

        $this->info("Wrote {$written} Trust Indicator snapshot row(s).");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
