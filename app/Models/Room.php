<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

#[Fillable(['number', 'floor', 'capacity', 'monthly_rent', 'status', 'has_attached_bath', 'has_balcony', 'notes'])]
class Room extends Model
{
    use HasFactory;

    public const MAX_CAPACITY = 6;

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'floor' => 'integer',
            'capacity' => 'integer',
            'monthly_rent' => 'integer',
            'has_attached_bath' => 'boolean',
            'has_balcony' => 'boolean',
        ];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class)->orderBy('label');
    }

    public function activeAllocations(): HasManyThrough
    {
        return $this->hasManyThrough(Allocation::class, Seat::class)
            ->where('allocations.status', AllocationStatus::Active);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public static function typeLabelFor(int $capacity): string
    {
        return match ($capacity) {
            1 => 'Single',
            2 => 'Double',
            3 => 'Triple',
            4 => 'Four seat',
            default => $capacity.' seat',
        };
    }

    public function typeLabel(): string
    {
        return self::typeLabelFor($this->capacity);
    }

    /**
     * Public path of the photo for a room size: public/images/hall/room-<type>.jpg,
     * e.g. room-double.jpg. Replace those files to show real photos of the hall.
     */
    public static function photoFor(int $capacity): string
    {
        $photo = 'images/hall/room-'.Str::slug(self::typeLabelFor($capacity)).'.jpg';

        return is_file(public_path($photo)) ? $photo : 'images/hall/hero.jpg';
    }

    public function isAvailable(): bool
    {
        return $this->status === RoomStatus::Available;
    }

    public function floorLabel(): string
    {
        return self::floorLabelFor($this->floor);
    }

    public static function floorLabelFor(int $floor): string
    {
        return $floor === 0 ? 'Ground floor' : 'Floor '.$floor;
    }

    /**
     * Seat labels for a room of the given size: A, B, C...
     *
     * @return list<string>
     */
    public static function seatLabels(int $capacity): array
    {
        return array_slice(range('A', 'Z'), 0, $capacity);
    }
}
