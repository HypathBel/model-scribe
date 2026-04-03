<?php

namespace HypathBel\ModelScribe\DTOs;

use HypathBel\ModelScribe\Enums\ScribeEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Immutable value-object passed from the observer to every driver.
 */
final class LogEntry
{
    public readonly string $batchUuid;

    public function __construct(
        public readonly ScribeEvent $event,
        public readonly string $logName,
        public readonly ?string $description,
        public readonly ?Model $subject,
        public readonly ?Model $causer,
        public readonly array $properties,   // ['old' => [...], 'attributes' => [...]]
        public readonly array $tags = [],
        public readonly ?string $url = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        ?string $batchUuid = null,
    ) {
        $this->batchUuid = $batchUuid ?? (string) Str::uuid();
    }

    public function withLogName(string $logName): self
    {
        return new self(
            event: $this->event,
            logName: $logName,
            description: $this->description,
            subject: $this->subject,
            causer: $this->causer,
            properties: $this->properties,
            tags: $this->tags,
            url: $this->url,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            batchUuid: $this->batchUuid,
        );
    }
}
