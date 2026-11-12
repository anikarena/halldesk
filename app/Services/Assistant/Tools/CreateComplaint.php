<?php

namespace App\Services\Assistant\Tools;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Models\User;
use App\Services\Assistant\Tool;
use App\Services\ComplaintService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateComplaint extends Tool
{
    public function __construct(private ComplaintService $complaints) {}

    public function name(): string
    {
        return 'create_complaint';
    }

    public function description(): string
    {
        return 'Submit a complaint about a problem in the student\'s room or the hall. Ask first if it is not clear what is wrong. '
            .'The user confirms on screen before it is submitted.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'enum' => array_column(ComplaintCategory::cases(), 'value')],
                'title' => ['type' => 'string', 'description' => 'Short English title, at most 80 characters, for example "Ceiling fan not working".'],
                'description' => ['type' => 'string', 'description' => 'What is wrong, in English, in one to three sentences.'],
                'priority' => ['type' => 'string', 'enum' => array_column(ComplaintPriority::cases(), 'value'), 'description' => 'high only for safety risks or no water or power. Default medium.'],
            ],
            'required' => ['category', 'title', 'description', 'priority'],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isResident();
    }

    public function changesData(): bool
    {
        return true;
    }

    public function describe(User $user, array $args): string
    {
        $data = $this->validated($args);

        return 'Submit a complaint: "'.$data['title'].'" ('.ComplaintCategory::from($data['category'])->label().', '
            .strtolower(ComplaintPriority::from($data['priority'])->label()).' priority)';
    }

    public function run(User $user, array $args): array
    {
        $complaint = $this->complaints->create($user, $this->validated($args));

        return [
            'done' => true,
            'reference' => $complaint->reference(),
            'status' => $complaint->status->label(),
            '_links' => [['label' => 'Complaint '.$complaint->reference(), 'url' => route('student.complaints.show', $complaint)]],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{category: string, title: string, description: string, priority: string}
     */
    private function validated(array $args): array
    {
        return Validator::make($args, [
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'priority' => ['required', Rule::enum(ComplaintPriority::class)],
        ])->validate();
    }
}
