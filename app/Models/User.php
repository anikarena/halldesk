<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Linked hall office Telegram chat (staff bot), empty until connected.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['telegram_chat_id' => null];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class)->latest('start_date');
    }

    public function activeAllocation(): HasOne
    {
        return $this->hasOne(Allocation::class)->where('status', AllocationStatus::Active);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SeatApplication::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Complaints this staff member is handling.
     */
    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function mealOffs(): HasMany
    {
        return $this->hasMany(MealOff::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    /**
     * Devices that get this user's notifications as push alerts.
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::Staff;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    /**
     * Admin or staff, the people who run the hall.
     */
    public function isManager(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function isResident(): bool
    {
        return $this->isStudent() && $this->activeAllocation()->exists();
    }

    /**
     * First name without a title: "Dr. Mahbubur Rahman" gives "Mahbubur".
     */
    public function firstName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $titles = ['dr.', 'prof.', 'md.', 'mr.', 'mrs.', 'ms.', 'engr.'];
        $parts = array_values(array_filter($parts, fn ($p) => ! in_array(strtolower($p), $titles, true)));

        return $parts[0] ?? $this->name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? end($parts) : '';

        return strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }

    public function scopeStudents(Builder $query): void
    {
        $query->where('role', Role::Student);
    }

    public function scopeManagers(Builder $query): void
    {
        $query->whereIn('role', [Role::Admin, Role::Staff]);
    }

    public function scopeResidents(Builder $query): void
    {
        $query->students()->whereHas('activeAllocation');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('profile', fn (Builder $p) => $p->where('student_id', 'like', "%{$term}%"));
        });
    }
}
