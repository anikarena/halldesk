<?php

namespace App\Services\Assistant;

use App\Exceptions\HallException;
use App\Models\User;
use App\Services\Assistant\Tools\CreateComplaint;
use App\Services\Assistant\Tools\DuesList;
use App\Services\Assistant\Tools\HallOverview;
use App\Services\Assistant\Tools\MealCount;
use App\Services\Assistant\Tools\MyBills;
use App\Services\Assistant\Tools\MyComplaints;
use App\Services\Assistant\Tools\MyMeals;
use App\Services\Assistant\Tools\MyRoom;
use App\Services\Assistant\Tools\OpenComplaints;
use App\Services\Assistant\Tools\RemindDues;
use App\Services\Assistant\Tools\SearchHallRules;
use App\Services\Assistant\Tools\SetMeals;
use App\Services\Assistant\Tools\VacantSeats;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ask AI: the hall assistant (the agent).
 *
 * The loop: send the conversation and the user's tools to the model. If the
 * model asks for a tool, run it and send the result back; repeat until the
 * model answers in words. A tool that changes data is not run straight away:
 * the user sees a Confirm / Cancel card first, and decide() carries on from there.
 */
class Assistant
{
    private const MAX_STEPS = 6;

    /** @var list<class-string<Tool>> */
    private const TOOLS = [
        SearchHallRules::class,
        MyRoom::class,
        MyMeals::class,
        SetMeals::class,
        MyBills::class,
        MyComplaints::class,
        CreateComplaint::class,
        HallOverview::class,
        VacantSeats::class,
        MealCount::class,
        DuesList::class,
        OpenComplaints::class,
        RemindDues::class,
    ];

    public function __construct(private Gemini $gemini) {}

    public function enabled(): bool
    {
        return $this->gemini->enabled();
    }

    /**
     * The tools this user may use. Students never get the office tools.
     *
     * @return Collection<string, Tool>
     */
    public function toolsFor(User $user): Collection
    {
        return collect(self::TOOLS)
            ->map(fn (string $class) => app($class))
            ->filter(fn (Tool $tool) => $tool->allows($user))
            ->keyBy(fn (Tool $tool) => $tool->name());
    }

    public function send(User $user, Conversation $conversation, string $message): Turn
    {
        $conversation->contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        return $this->loop($user, $conversation);
    }

    /**
     * The user pressed Confirm or Cancel on the waiting change.
     */
    public function decide(User $user, Conversation $conversation, bool $approved): Turn
    {
        $pending = $conversation->pending;
        $conversation->pending = null;
        $links = [];

        $tool = $this->toolsFor($user)->get($pending['name']);
        $result = match (true) {
            ! $tool => ['error' => 'This tool is not available for this user.'],
            $approved => ['confirmed_by_user' => true, ...$this->execute($user, $tool, $pending['args'], $links)],
            default => ['cancelled' => true, 'note' => 'The user pressed Cancel. Nothing was changed.'],
        };

        $responses = $pending['responses'];
        $responses[$pending['index']] = $this->response($pending['name'], $result);
        ksort($responses);
        $conversation->contents[] = ['role' => 'user', 'parts' => array_values($responses)];

        try {
            return $this->loop($user, $conversation, $links);
        } catch (AssistantUnavailable) {
            // The change already happened: report it plainly and keep the history valid
            $text = match (true) {
                isset($result['error']) => 'That did not work: '.$result['error'],
                $approved => 'Done: '.$pending['summary'].'.',
                default => 'Cancelled. Nothing was changed.',
            };
            $conversation->contents[] = ['role' => 'model', 'parts' => [['text' => $text]]];

            return new Turn($text, array_values($links));
        }
    }

    /**
     * @param  array<string, array{label: string, url: string}>  $links
     */
    private function loop(User $user, Conversation $conversation, array $links = []): Turn
    {
        $tools = $this->toolsFor($user);
        $declarations = $tools->map(fn (Tool $tool) => $tool->declaration())->values()->all();
        $system = $this->instructions($user);

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $content = $this->gemini->generate($system, $conversation->contents, $declarations);
            $conversation->contents[] = $content;

            $calls = collect($content['parts'])->filter(fn (array $part) => isset($part['functionCall']))->pluck('functionCall')->values();
            if ($calls->isEmpty()) {
                return new Turn($this->text($content) ?: 'Sorry, I could not answer that. Please ask again.', array_values($links));
            }

            $responses = [];
            $pending = null;
            foreach ($calls as $i => $call) {
                $name = (string) ($call['name'] ?? '');
                $args = (array) ($call['args'] ?? []);
                $tool = $tools->get($name);

                if (! $tool) {
                    $responses[$i] = $this->response($name, ['error' => 'This tool is not available for this user.']);
                } elseif (! $tool->changesData()) {
                    $responses[$i] = $this->response($name, $this->execute($user, $tool, $args, $links));
                } elseif ($pending) {
                    $responses[$i] = $this->response($name, ['error' => 'Only one change can wait for confirmation at a time. Offer to do this one afterwards.']);
                } else {
                    try {
                        $pending = ['index' => $i, 'name' => $name, 'args' => $args, 'summary' => $tool->describe($user, $args)];
                    } catch (HallException|ValidationException $e) {
                        $responses[$i] = $this->response($name, ['error' => $this->errorMessage($e)]);
                    }
                }
            }

            if ($pending) {
                $pending['id'] = Str::random(16);
                $pending['responses'] = $responses;
                $conversation->pending = $pending;

                // Often no words here: the confirm card says it all
                return new Turn(
                    $this->text($content),
                    array_values($links),
                    ['id' => $pending['id'], 'summary' => $pending['summary']],
                );
            }

            ksort($responses);
            $conversation->contents[] = ['role' => 'user', 'parts' => array_values($responses)];
        }

        return new Turn('Sorry, that took too many steps. Please ask in a simpler way.', array_values($links));
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, array{label: string, url: string}>  $links
     * @return array<string, mixed>
     */
    private function execute(User $user, Tool $tool, array $args, array &$links): array
    {
        try {
            $result = $tool->run($user, $args);
        } catch (HallException|ValidationException $e) {
            return ['error' => $this->errorMessage($e)];
        }

        foreach ($result['_links'] ?? [] as $link) {
            $links[$link['url']] = $link;
        }
        unset($result['_links']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function response(string $name, array $result): array
    {
        return ['functionResponse' => ['name' => $name, 'response' => ['result' => $result]]];
    }

    private function errorMessage(HallException|ValidationException $e): string
    {
        return $e instanceof ValidationException ? collect($e->errors())->flatten()->implode(' ') : $e->getMessage();
    }

    /**
     * The model's words, tidied for the chat bubble: no Markdown stars, no dashes.
     *
     * @param  array{parts: list<array<string, mixed>>}  $content
     */
    private function text(array $content): string
    {
        $text = collect($content['parts'])
            ->reject(fn (array $part) => $part['thought'] ?? false)
            ->pluck('text')
            ->filter()
            ->implode(''); // one answer can arrive split over several parts

        $text = str_replace(['**', '__'], '', $text);
        $text = (string) preg_replace('/^\s*[-*]\s+/m', '• ', $text);
        $text = (string) preg_replace('/\s*[—–]\s*/u', ', ', $text);

        return trim($text);
    }

    private function instructions(User $user): string
    {
        $who = $user->isStudent()
            ? ($user->isResident() ? 'a resident student' : 'a student without a seat yet')
            : 'hall office '.strtolower($user->role->label());

        return implode("\n", [
            'You are the HallDesk assistant of '.setting('hall_name').', '.setting('university_name').'.',
            "You are talking to {$user->name}, {$who}.",
            'Now: '.now()->format('l, j F Y, g:i A').' (Bangladesh time). Tomorrow is '.now()->addDay()->format('l, j F Y').'.',
            '',
            'Rules:',
            '1. Only help with this hall: seat and room, meals, bills and payments, complaints, notices, visitors and hall rules. For anything else, such as homework, code or general chat, say in one sentence that you can only help with hall matters.',
            '2. Get facts from the tools. Never guess numbers, dates, money, names or rules.',
            '3. For any question about rules, policies, procedures or notices, call search_hall_rules and answer only from the passages it returns. If they do not answer the question, say the rulebook does not cover it and give the hall office number '.setting('contact_phone').'.',
            '4. You only see and change the signed in user\'s own data, and only what your tools allow. Never say something was done unless a tool result says so.',
            '5. Changes (meals, complaints, reminders) appear on screen with a Confirm button. Call the tool directly with complete arguments; do not ask "are you sure" yourself. If something needed is missing, ask one short question first. When the result says confirmed_by_user and done, the change is already saved: tell the user it is done. If it says cancelled, nothing changed.',
            '6. Work out dates yourself from the current date ("tomorrow", "next Friday", "3 days from Sunday"). Use both lunch and dinner when the user does not name a meal.',
            '7. Reply in the language the user writes in: English, Bangla or Banglish (Bangla in Latin letters).',
            '8. Be short and friendly: one to four sentences, or a short list with "• " bullets. Money like ৳8,280. Dates like Tue, 10 Nov.',
            '9. Plain text only: no Markdown, no tables, no emoji, and never use dash characters as punctuation.',
        ]);
    }
}
