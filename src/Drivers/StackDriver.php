<?php

namespace HypathBel\ModelScribe\Drivers;

use HypathBel\ModelScribe\Contracts\DriverInterface;
use HypathBel\ModelScribe\DriverManager;
use HypathBel\ModelScribe\DTOs\LogEntry;

class StackDriver implements DriverInterface
{
    /** @var DriverInterface[] */
    protected array $drivers;

    public function __construct(protected array $config, DriverManager $manager)
    {
        $driverNames = $config['drivers'] ?? [];
        $this->drivers = array_map(
            fn (string $name) => $manager->driver($name),
            $driverNames
        );
    }

    public function log(LogEntry $entry): void
    {
        foreach ($this->drivers as $driver) {
            $driver->log($entry);
        }
    }

    public function prune(): int
    {
        return array_sum(
            array_map(fn (DriverInterface $d) => $d->prune(), $this->drivers)
        );
    }
}
