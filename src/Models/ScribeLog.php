<?php

namespace HypathBel\ModelScribe\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $log_name
 * @property string $event
 * @property string|null $description
 * @property array|null $properties
 * @property array|null $tags
 * @property string|null $batch_uuid
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScribeLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'tags' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Convenience accessors ───────────────────────────────────────────────

    /** Attributes captured after the event. */
    public function getNewAttributesAttribute(): array
    {
        return $this->properties['attributes'] ?? [];
    }

    /** Attributes as they were before the event. */
    public function getOldAttributesAttribute(): array
    {
        return $this->properties['old'] ?? [];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }

    public function scopeForCauser($query, Model $causer)
    {
        return $query->where('causer_type', get_class($causer))
            ->where('causer_id', $causer->getKey());
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeInLog($query, string $logName)
    {
        return $query->where('log_name', $logName);
    }

    public function getTable(): string
    {
        // Allow dynamic table-name resolution via config.
        return $this->table ?? config('model-scribe.drivers.database.table', 'model_scribe_logs');
    }
}
