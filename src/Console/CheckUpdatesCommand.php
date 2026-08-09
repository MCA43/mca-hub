<?php

namespace Mca\Hub\Console;

use Illuminate\Console\Command;
use Mca\Hub\Services\PackageCatalog;
use Mca\Hub\Services\UpdateChecker;
use Mca\Hub\Support\McaHubLocale;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mca:hub:check-updates')]
class CheckUpdatesCommand extends Command
{
    protected $signature = 'mca:hub:check-updates {--fresh : Clear update cache first}';

    protected $description = 'Check GitHub for newer MCA package releases';

    public function handle(PackageCatalog $catalog, UpdateChecker $checker): int
    {
        McaHubLocale::apply();

        if (! config('hub.updates.enabled', true)) {
            $this->components->warn(mca_hub('updates.disabled'));

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $checker->forgetAll();
        }

        // packagesForCurrentFramework already enriches when updates.enabled
        $packages = $catalog->packagesForCurrentFramework();
        $rows = [];
        $available = 0;

        foreach ($packages as $pkg) {
            if (! ($pkg['installed'] ?? false)) {
                continue;
            }

            $status = (string) ($pkg['update_status'] ?? 'unknown');
            if (in_array($status, ['update_available', 'path_linked'], true)) {
                $available++;
            }

            $rows[] = [
                $pkg['name'],
                $pkg['version'] ?? '—',
                $pkg['latest_version'] ?? '—',
                $status,
                ($pkg['can_update'] ?? false) ? 'yes' : 'no',
            ];
        }

        $this->table(
            ['Package', 'Installed', 'Latest', 'Status', 'Can update'],
            $rows
        );

        $this->components->info(mca_hub('updates.check_done', ['count' => $available]));

        return self::SUCCESS;
    }
}
