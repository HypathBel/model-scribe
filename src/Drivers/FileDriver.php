<?php

namespace HypathBel\ModelScribe\Drivers;

use HypathBel\ModelScribe\Contracts\DriverInterface;
use HypathBel\ModelScribe\DTOs\LogEntry;
use Illuminate\Support\Facades\Log;

class FileDriver implements DriverInterface
{
    public function __construct(protected array $config) {}

    public function log(LogEntry $entry): void
    {
        $channel = $this->config['channel'] ?? 'daily';

        $context = [
            'log_name' => $entry->logName,
            'event' => $entry->event->value,
            'subject' => $entry->subject
                ? [get_class($entry->subject) => $entry->subject->getKey()]
                : null,
            'causer' => $entry->causer
                ? [get_class($entry->causer) => $entry->causer->getKey()]
                : null,
            'properties' => $entry->properties,
            'tags' => $entry->tags,
            'batch_uuid' => $entry->batchUuid,
            'url' => $entry->url,
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent,
        ];

        $message = $entry->description
            ?? sprintf('[%s] %s', $entry->event->value, $entry->logName);

        Log::channel($channel)->info($message, $context);
    }

    /** File driver delegates rotation to Laravel's built-in log handling. */
    public function prune(): int
    {
        return 0;
    }
}
