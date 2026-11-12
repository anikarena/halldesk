<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'department', 'program', 'session', 'year_of_study', 'cgpa', 'gender',
    'date_of_birth', 'blood_group', 'home_district', 'permanent_address',
    'guardian_name', 'guardian_relation', 'guardian_phone',
])]
class StudentProfile extends Model
{
    use HasFactory;

    /** Departments of City University, as listed on cityuniversity.ac.bd */
    public const DEPARTMENTS = [
        'Computer Science and Engineering',
        'Electrical and Electronic Engineering',
        'Mechanical Engineering',
        'Civil Engineering',
        'Textile Engineering',
        'Pharmacy',
        'Public Health',
        'Business Administration',
        'English',
        'Law',
        'Agriculture',
    ];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => DateOnly::class,
            'cgpa' => 'decimal:2',
            'year_of_study' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yearLabel(): string
    {
        $suffix = match ($this->year_of_study) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };

        return $this->year_of_study.$suffix.' year';
    }
}
