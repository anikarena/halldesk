<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\AllocationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'seat_id', 'application_id', 'start_date', 'end_date', 'status', 'end_reason',
    'allocated_by', 'ended_by', 'active_seat_id', 'active_user_id',
])]
class Allocation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AllocationStatus::class,
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SeatApplication::class, 'application_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function isActive(): bool
    {
        return $this->status === AllocationStatus::Active;
    }

    // Columns are qualified with the table name so the scopes still work when
    // the query joins seats and rooms (rooms also has a status column).
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), AllocationStatus::Active);
    }

    /**
     * Allocations that cover at least one day between $from and $to (inclusive).
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate($this->qualifyColumn('start_date'), '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('end_date'))->orWhereDate($this->qualifyColumn('end_date'), '>=', $from));
    }
}
