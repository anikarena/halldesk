<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\Meal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'meal'])]
class MealOff extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'meal' => Meal::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
