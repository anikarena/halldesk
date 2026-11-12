<?php

namespace App\Services\Assistant\Tools;

use App\Enums\Meal;
use App\Exceptions\HallException;
use App\Models\User;
use App\Services\Assistant\Tool;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SetMeals extends Tool
{
    public function __construct(private MealService $meals) {}

    public function name(): string
    {
        return 'set_meals';
    }

    public function description(): string
    {
        return 'Turn the student\'s lunch, dinner or both ON or OFF for one date or a range of dates (for example a trip home). '
            .'The user confirms on screen before anything changes. Locked dates are skipped.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'First date, Y-m-d.'],
                'to' => ['type' => 'string', 'description' => 'Last date, Y-m-d. Same as from for one day.'],
                'meals' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['lunch', 'dinner']], 'description' => 'Which meals. Both when the user does not say.'],
                'on' => ['type' => 'boolean', 'description' => 'true to turn ON, false to turn OFF.'],
            ],
            'required' => ['from', 'to', 'meals', 'on'],
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
        [$from, $to, $meals, $on] = $this->validated($args);

        $open = collect(CarbonPeriod::create($from, $to))->filter(fn ($day) => $this->meals->isChangeable(CarbonImmutable::parse($day)));
        if ($open->isEmpty()) {
            $next = $this->meals->firstChangeableDate();
            throw new HallException("Those dates are locked. The first date you can still change is {$next->format('D, j M')}.");
        }

        $what = collect($meals)->map(fn (Meal $meal) => strtolower($meal->label()))->implode(' and ');
        $when = $from->equalTo($to)
            ? 'on '.$from->format('D, j M')
            : 'from '.$from->format('D, j M').' to '.$to->format('D, j M');

        return ucfirst($what).' '.($on ? 'ON' : 'OFF').' '.$when;
    }

    public function run(User $user, array $args): array
    {
        [$from, $to, $meals, $on] = $this->validated($args);

        $changed = $this->meals->setRange($user, $from, $to, $meals, $on);
        $total = (int) $from->diffInDays($to) + 1;

        return [
            'done' => $changed > 0,
            'days_changed' => $changed,
            'locked_days_skipped' => $total - $changed,
            'meals' => array_map(fn (Meal $meal) => $meal->value, $meals),
            'now' => $on ? 'ON' : 'OFF',
            '_links' => [['label' => 'Meals', 'url' => route('student.meals')]],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: list<Meal>, 3: bool}
     */
    private function validated(array $args): array
    {
        $data = Validator::make($args, [
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'meals' => ['required', 'array', 'min:1'],
            'meals.*' => [Rule::enum(Meal::class)],
            'on' => ['required', 'boolean'],
        ], [
            'to.before_or_equal' => 'Meals can be planned up to 60 days ahead.',
        ])->validate();

        return [
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            array_map(fn ($m) => Meal::from($m), array_values(array_unique($data['meals']))),
            (bool) $data['on'],
        ];
    }
}
