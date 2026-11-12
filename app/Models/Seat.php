<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['room_id', 'label'])]
class Seat extends Model
{
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function activeAllocation(): HasOne
    {
        return $this->hasOne(Allocation::class)->where('status', AllocationStatus::Active);
    }

    /**
     * "204-B"
     */
    public function code(): string
    {
        return $this->room->number.'-'.$this->label;
    }

    /**
     * Seats nobody holds, in rooms that are not under maintenance.
     */
    public function scopeVacant(Builder $query): void
    {
        $query->whereDoesntHave('activeAllocation')
            ->whereHas('room', fn (Builder $q) => $q->where('status', RoomStatus::Available));
    }
}
