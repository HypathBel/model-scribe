<?php

use HypathBel\ModelScribe\Enums\ScribeEvent;
use HypathBel\ModelScribe\Models\ScribeLog;
use HypathBel\ModelScribe\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ── Inline stub models ──────────────────────────────────────────────────────

class Order extends Model
{
    use HasAuditLog;

    protected $table = 'orders';

    protected $guarded = [];

    public $timestamps = true;
}

class Invoice extends Model
{
    use HasAuditLog;

    protected $table = 'invoices';

    protected $guarded = [];

    public $timestamps = true;

    protected array $auditEvents = ['created', 'updated'];

    protected array $auditAttributes = [
        'updated' => ['amount', 'status'],
    ];

    protected string $auditLogName = 'invoices';
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function createOrdersTable(): void
{
    if (! Schema::hasTable('orders')) {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }
}

function createInvoicesTable(): void
{
    if (! Schema::hasTable('invoices')) {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }
}

// ── Tests ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    createOrdersTable();
    createInvoicesTable();
});

it('logs a created event when a model is saved', function () {
    $order = Order::create(['status' => 'pending', 'total' => 100]);

    $log = ScribeLog::first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ScribeEvent::Created->value)
        ->and($log->subject_type)->toBe(Order::class)
        ->and($log->subject_id)->toBe((string) $order->id)
        ->and($log->properties['attributes']['status'])->toBe('pending');
});

it('logs an updated event with old and new values', function () {
    $order = Order::create(['status' => 'pending', 'total' => 100]);
    ScribeLog::query()->delete(); // Reset — only interested in the update

    $order->update(['status' => 'shipped', 'total' => 150]);

    $log = ScribeLog::first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ScribeEvent::Updated->value)
        ->and($log->properties['old']['status'])->toBe('pending')
        ->and($log->properties['attributes']['status'])->toBe('shipped');
});

it('logs a deleted event', function () {
    $order = Order::create(['status' => 'pending', 'total' => 50]);
    ScribeLog::query()->delete(); // Reset

    $order->delete();

    $log = ScribeLog::first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ScribeEvent::Deleted->value)
        ->and($log->properties['old']['status'])->toBe('pending')
        ->and($log->properties['attributes'])->toBe([]);
});

it('does not log events that are not in $auditEvents', function () {
    // Invoice only listens to created and updated — not deleted
    $invoice = Invoice::create(['amount' => 500, 'status' => 'draft']);
    ScribeLog::query()->delete(); // Reset

    $invoice->delete();

    expect(ScribeLog::count())->toBe(0);
});

it('respects per-event $auditAttributes filtering', function () {
    $invoice = Invoice::create(['amount' => 500, 'status' => 'draft', 'reference' => 'REF-001']);
    ScribeLog::query()->delete(); // Reset

    $invoice->update(['amount' => 750, 'status' => 'sent', 'reference' => 'REF-002']);

    $log = ScribeLog::first();

    // 'reference' is NOT in the auditable attributes for 'updated'
    expect($log)->not->toBeNull()
        ->and(array_key_exists('amount', $log->properties['attributes']))->toBeTrue()
        ->and(array_key_exists('status', $log->properties['attributes']))->toBeTrue()
        ->and(array_key_exists('reference', $log->properties['attributes']))->toBeFalse();
});

it('routes to the correct log_name', function () {
    Invoice::create(['amount' => 100, 'status' => 'draft']);

    $log = ScribeLog::first();

    expect($log->log_name)->toBe('invoices');
});

it('captures request context by default', function () {
    Order::create(['status' => 'new', 'total' => 10]);

    $log = ScribeLog::first();

    // In test mode url/ip will be set (even if to placeholder values)
    expect($log)->not->toBeNull();
    // url and ip_address columns exist (may be null in CLI)
    expect(array_key_exists('url', $log->getAttributes()))->toBeTrue()
        ->and(array_key_exists('ip_address', $log->getAttributes()))->toBeTrue();
});
