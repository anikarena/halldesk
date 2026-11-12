<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['complaint_id', 'user_id', 'body', 'from_status', 'to_status'])]
class ComplaintUpdate extends Model
{
    protected function casts(): array
    {
        return [
            'from_status' => ComplaintStatus::class,
            'to_status' => ComplaintStatus::class,
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
