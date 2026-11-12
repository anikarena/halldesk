<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'user_id', 'kind', 'period', 'title', 'due_date', 'total', 'paid', 'status', 'created_by'])]
class Invoice extends Model
{
    public const KIND_MONTHLY = 'monthly';

    public const KIND_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_date' => DateOnly::class,
            'total' => 'integer',
            'paid' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A readable number like INV-000042, based on the row id
        static::created(function (Invoice $invoice) {
            if (! $invoice->number) {
                $invoice->number = 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
                $invoice->saveQuietly();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paid);
    }

    /**
     * Money already sent by the student but not verified yet.
     */
    public function pendingAmount(): int
    {
        return (int) $this->payments()->where('status', PaymentStatus::Pending)->sum('amount');
    }

    public function isOpen(): bool
    {
        return in_array($this->status->value, InvoiceStatus::open(), true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::open());
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->whereDate('due_date', '<', today());
    }
}
