<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'visitor_name', 'phone', 'relation', 'purpose', 'check_in_at', 'check_out_at', 'recorded_by'])]
class Visitor extends Model
{
    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    /**
     * The resident who was visited.
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isInside(): bool
    {
        return $this->check_out_at === null;
    }

    public function scopeInside(Builder $query): void
    {
        $query->whereNull('check_out_at');
    }
}
