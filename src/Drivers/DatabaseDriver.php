<?php

namespace HypathBel\ModelScribe\Drivers;

use HypathBel\ModelScribe\Contracts\DriverInterface;
use HypathBel\ModelScribe\DTOs\LogEntry;
use HypathBel\ModelScribe\Models\ScribeLog;
use Illuminate\Support\Facades\DB;

class DatabaseDriver implements DriverInterface
{
    public function __construct(protected array $config) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve the target tables and DB connections for a given log name.
     *
     * Resolution order:
     *   1. Named store in config (model-scribe.drivers.database.stores.<logName>)
     *   2. Fallback to global default table + connection
     *
     * @return array<array{table: string, connection: string|null}>
     */
    protected function resolveStore(string $logName): array
    {
        $stores = $this->config['stores'] ?? [];

        if (isset($stores[$logName])) {
            $store = $stores[$logName];
            $connection = $store['connection'] ?? $this->config['connection'] ?? null;
            $tables = $store['tables'] ?? (isset($store['table']) ? [$store['table']] : [$this->config['table'] ?? 'model_scribe_logs']);

            return array_map(fn (string $table) => [
                'table' => $table,
                'connection' => $connection,
            ], $tables);
        }

        return [[
            'table' => $this->config['table'] ?? 'model_scribe_logs',
            'connection' => $this->config['connection'] ?? null,
        ]];
    }

    // ── DriverInterface ───────────────────────────────────────────────────────

    public function log(LogEntry $entry): void
    {
        $resolvedStores = $this->resolveStore($entry->logName);

        foreach ($resolvedStores as $store) {
            $table = $store['table'];
            $connection = $store['connection'];

            $model = new ScribeLog;

            if ($connection) {
                $model->setConnection($connection);
            }

            $model->setTable($table);

            $model->fill([
                'log_name' => $entry->logName,
                'event' => $entry->event->value,
                'description' => $entry->description,

                'causer_type' => $entry->causer ? get_class($entry->causer) : null,
                'causer_id' => $entry->causer?->getKey(),

                'subject_type' => $entry->subject ? get_class($entry->subject) : null,
                'subject_id' => $entry->subject?->getKey(),

                'properties' => $entry->properties,
                'tags' => $entry->tags ?: null,
                'batch_uuid' => $entry->batchUuid,
                'url' => $entry->url,
                'ip_address' => $entry->ipAddress,
                'user_agent' => $entry->userAgent,
            ])->save();
        }
    }

    public function prune(): int
    {
        $retention = $this->config['retention'] ?? [];
        $type = $retention['type'] ?? 'permanent';

        if ($type === 'permanent') {
            return 0;
        }

        $deleted = 0;

        // Collect all tables to prune: the default + every store.
        $targets = $this->allStoreTables();

        foreach ($targets as ['table' => $table, 'connection' => $connection]) {
            $query = DB::connection($connection)->table($table);

            if ($type === 'days') {
                $days = (int) ($retention['days'] ?? 90);
                $deleted += $query->where('created_at', '<', now()->subDays($days))->delete();
            } elseif ($type === 'rotating') {
                $keep = (int) ($retention['keep'] ?? 500);
                $cutoffId = DB::connection($connection)
                    ->table($table)
                    ->orderByDesc('id')
                    ->skip($keep)
                    ->value('id');

                if ($cutoffId !== null) {
                    $deleted += $query->where('id', '<=', $cutoffId)->delete();
                }
            }
        }

        return $deleted;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Returns all distinct table+connection pairs managed by this driver.
     *
     * @return array<array{table: string, connection: string|null}>
     */
    protected function allStoreTables(): array
    {
        $defaultConnection = $this->config['connection'] ?? null;
        $defaultTable = $this->config['table'] ?? 'model_scribe_logs';

        $targets = [
            ['table' => $defaultTable, 'connection' => $defaultConnection],
        ];

        foreach ($this->config['stores'] ?? [] as $store) {
            $connection = $store['connection'] ?? $defaultConnection;
            $tables = $store['tables'] ?? (isset($store['table']) ? [$store['table']] : []);

            foreach ($tables as $table) {
                $targets[] = ['table' => $table, 'connection' => $connection];
            }
        }

        // Deduplicate by table+connection (stores may point to the same table).
        return array_values(array_unique($targets, SORT_REGULAR));
    }
}
