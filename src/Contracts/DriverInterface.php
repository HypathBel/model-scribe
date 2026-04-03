<?php

namespace HypathBel\ModelScribe\Contracts;

use HypathBel\ModelScribe\DTOs\LogEntry;

interface DriverInterface
{
    /**
     * Persist or dispatch a log entry.
     */
    public function log(LogEntry $entry): void;

    /**
     * Prune old entries according to the driver's retention policy.
     * Implementations that don't support pruning can be a no-op.
     */
    public function prune(): int;
}
