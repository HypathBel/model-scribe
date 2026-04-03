<?php

namespace HypathBel\ModelScribe\Traits;

use HypathBel\ModelScribe\Observers\ModelScribeObserver;

/**
 * Drop this trait into any Eloquent model to enable audit logging.
 *
 * ── Minimal usage ──────────────────────────────────────────────────────────
 *
 *   class Invoice extends Model
 *   {
 *       use HasAuditLog;
 *   }
 *
 * ── Full customisation ─────────────────────────────────────────────────────
 *
 *   class Invoice extends Model
 *   {
 *       use HasAuditLog;
 *
 *       // Only log these events
 *       protected array $auditEvents = ['created', 'updated'];
 *
 *       // Per-event attribute lists (null / '*' = all)
 *       protected array $auditAttributes = [
 *           'created' => ['amount', 'status', 'user_id'],
 *           'updated' => ['amount', 'status'],
 *       ];
 *
 *       // Route to a different driver
 *       protected ?string $auditDriver = 'file';
 *
 *       // Route to a specific table / log name
 *       protected string $auditLogName = 'invoices';
 *
 *       // Extra tags stored alongside each entry
 *       protected array $auditTags = ['billing', 'finance'];
 *   }
 */
trait HasAuditLog
{
    /**
     * Eloquent events to capture.
     *
     * @var array<string>
     */
    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * Which attributes to capture, keyed by event name.
     * Use '*' or omit the key to capture all attributes for that event.
     *
     * @var array<string, string[]|'*'>|'*'
     */
    protected array $auditAttributes = [];

    /**
     * Override the package default driver for this model.
     */
    protected ?string $auditDriver = null;

    /**
     * Log name / table routing key.
     * Maps to the `log_name` column and (for DB driver) the table name when
     * different from the global default.
     */
    protected string $auditLogName = 'default';

    /**
     * Extra tags to store with every log entry from this model.
     *
     * @var array<string>
     */
    protected array $auditTags = [];

    // ── Boot ─────────────────────────────────────────────────────────────────

    public static function bootHasAuditLog(): void
    {
        static::observe(ModelScribeObserver::class);
    }

    // ── Accessors for the observer ───────────────────────────────────────────

    public function getAuditEvents(): array
    {
        return $this->auditEvents;
    }

    public function getAuditDriver(): ?string
    {
        return $this->auditDriver;
    }

    public function getAuditLogName(): string
    {
        return $this->auditLogName;
    }

    public function getAuditTags(): array
    {
        return $this->auditTags;
    }

    /**
     * Returns the list of attributes to capture for a given event,
     * or null to capture all attributes.
     *
     * @return string[]|null
     */
    public function getAuditableAttributes(string $event): ?array
    {
        if (empty($this->auditAttributes)) {
            return null; // log everything
        }

        // If it's a flat list (not keyed by event name), apply to all events
        if (isset($this->auditAttributes[0])) {
            return $this->auditAttributes;
        }

        $perEvent = $this->auditAttributes[$event] ?? '*';

        return $perEvent === '*' ? null : (array) $perEvent;
    }
}
