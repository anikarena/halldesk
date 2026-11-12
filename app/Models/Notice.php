<?php

namespace App\Models;

use App\Enums\NoticeCategory;
use App\Enums\NoticePriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body', 'category', 'priority', 'is_pinned', 'is_public', 'published_at', 'expires_at', 'created_by'])]
class Notice extends Model
{
    protected function casts(): array
    {
        return [
            'category' => NoticeCategory::class,
            'priority' => NoticePriority::class,
            'is_pinned' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Published and not expired.
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Pinned first, then urgent, important, normal, then newest.
     */
    public function scopeBoardOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')
            ->orderByRaw("case priority when 'urgent' then 3 when 'important' then 2 else 1 end desc")
            ->orderByDesc('published_at');
    }

    public function isLive(): bool
    {
        return $this->published_at->isPast() && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function isNew(): bool
    {
        return $this->published_at->greaterThan(now()->subDays(3));
    }

    public function isUrgent(): bool
    {
        return $this->priority === NoticePriority::Urgent;
    }
}
