<?php

namespace HypathBel\ModelScribe;

use HypathBel\ModelScribe\Contracts\DriverInterface;
use HypathBel\ModelScribe\Drivers\DatabaseDriver;
use HypathBel\ModelScribe\Drivers\FileDriver;
use HypathBel\ModelScribe\Drivers\StackDriver;
use InvalidArgumentException;

class DriverManager
{
    /** @var array<string, DriverInterface> */
    protected array $resolved = [];

    public function driver(?string $name = null): DriverInterface
    {
        $name ??= config('model-scribe.default', 'database');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        return $this->resolved[$name] = $this->make($name);
    }

    protected function make(string $name): DriverInterface
    {
        $config = config("model-scribe.drivers.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException(
                "ModelScribe driver [{$name}] is not configured."
            );
        }

        $driverType = $config['driver'] ?? $name;

        return match ($driverType) {
            'database' => new DatabaseDriver($config),
            'file' => new FileDriver($config),
            'stack' => new StackDriver($config, $this),
            default => $this->makeCustomDriver($driverType, $config),
        };
    }

    /**
     * Allow third-party drivers registered via extend().
     *
     * @var array<string, \Closure>
     */
    protected array $customCreators = [];

    public function extend(string $driver, \Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    protected function makeCustomDriver(string $driver, array $config): DriverInterface
    {
        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this);
        }

        throw new InvalidArgumentException(
            "ModelScribe driver [{$driver}] is not supported."
        );
    }

    /** Flush resolved driver cache (useful in tests). */
    public function flush(): void
    {
        $this->resolved = [];
    }
}
