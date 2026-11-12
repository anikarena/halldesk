<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'preferred_capacity', 'reason', 'special_needs', 'status', 'decision_note', 'reviewed_by', 'reviewed_at'])]
class SeatApplication extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'reviewed_at' => 'datetime',
            'preferred_capacity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(Allocation::class, 'application_id');
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }

    public function preferenceLabel(): string
    {
        return $this->preferred_capacity ? Room::typeLabelFor($this->preferred_capacity).' room' : 'Any room';
    }
}
