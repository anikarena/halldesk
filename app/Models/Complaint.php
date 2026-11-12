<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'room_id', 'category', 'title', 'description', 'priority', 'status', 'assigned_to', 'resolved_at'])]
class Complaint extends Model
{
    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'priority' => ComplaintPriority::class,
            'status' => ComplaintStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->oldest();
    }

    public function reference(): string
    {
        return 'C-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status !== ComplaintStatus::Resolved;
    }

    public function scopeUnresolved(Builder $query): void
    {
        $query->where('status', '!=', ComplaintStatus::Resolved);
    }
}
