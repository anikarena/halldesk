<?php

namespace App\Http\Requests;

use App\Enums\NoticeCategory;
use App\Enums\NoticePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoticeRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(NoticeCategory::class)],
            'priority' => ['required', Rule::enum(NoticePriority::class)],
            'is_pinned' => ['boolean'],
            'is_public' => ['boolean'],
            'published_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['expires_at.after' => 'The expiry time must be after the publish time.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_pinned' => $this->boolean('is_pinned'),
            'is_public' => $this->boolean('is_public'),
            // An empty publish time means "publish now"
            'published_at' => $this->input('published_at') ?: now()->format('Y-m-d H:i'),
        ]);
    }
}
