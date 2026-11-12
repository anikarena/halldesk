<?php

namespace App\Http\Requests;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        return [
            'number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('rooms', 'number')->ignore($room?->id)],
            'floor' => ['required', 'integer', 'between:0,30'],
            'capacity' => ['required', 'integer', 'between:1,'.Room::MAX_CAPACITY],
            'monthly_rent' => ['required', 'integer', 'between:0,100000'],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'has_attached_bath' => ['boolean'],
            'has_balcony' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['number.regex' => 'Use only letters and numbers for the room number, for example 204 or B12.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_attached_bath' => $this->boolean('has_attached_bath'),
            'has_balcony' => $this->boolean('has_balcony'),
        ]);
    }
}
