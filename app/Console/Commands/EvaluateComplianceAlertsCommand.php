<?php

namespace App\Console\Commands;

use App\Domain\Reporting\EvaluateComplianceAlerts;
use App\Domain\Tenancy\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

#[Signature('guidely:evaluate-compliance-alerts {--tenant= : Optional Tenant ULID to limit the run}')]
#[Description('Open or resolve Indicator alerts from live Trust and School metrics.')]
class EvaluateComplianceAlertsCommand extends Command
{
    public function handle(EvaluateComplianceAlerts $evaluate): int
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
        $created = 0;
        $resolved = 0;
        $skipped = 0;
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $outcome = $evaluate->handle($tenant, $cliRequest);
                $created += $outcome['created'];
                $resolved += $outcome['resolved'];

                if ($outcome['skipped']) {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error("Compliance alert evaluation failed for Tenant [{$tenant->id}]: {$exception->getMessage()}");
            }
        }

        $this->info("Opened {$created} alert(s). Resolved {$resolved}. Skipped {$skipped} Tenant(s) with alerts disabled.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
