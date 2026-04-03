<?php

namespace HypathBel\ModelScribe\Commands;

use HypathBel\ModelScribe\ModelScribe;
use Illuminate\Console\Command;

class PruneLogsCommand extends Command
{
    public $signature = 'model-scribe:prune
                            {--driver= : Prune a specific driver (default: the configured default)}';

    public $description = 'Prune stale ModelScribe audit log entries according to retention policy.';

    public function handle(ModelScribe $scribe): int
    {
        $driver = $this->option('driver') ?: null;

        $deleted = $scribe->prune($driver);

        $this->components->info("Pruned {$deleted} audit log record(s).");

        return self::SUCCESS;
    }
}
