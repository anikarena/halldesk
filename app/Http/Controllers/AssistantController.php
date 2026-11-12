<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\AssistantUnavailable;
use App\Services\Assistant\Conversation;
use App\Services\Assistant\Turn;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The Ask AI chat page. The chat lives in the session, so it is private to the
 * signed in user and ends when they sign out.
 */
class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant';

    public function __construct(private Assistant $assistant) {}

    public function index(Request $request): View
    {
        $conversation = $this->conversation($request);

        return view('assistant.index', [
            'enabled' => $this->assistant->enabled(),
            'messages' => $conversation->messages,
            'pending' => $conversation->pending ? Arr::only($conversation->pending, ['id', 'summary']) : null,
            'suggestions' => $this->suggestions($request->user()),
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $conversation = $this->conversation($request);

        if ($conversation->pending) {
            return response()->json(['message' => 'Please press Confirm or Cancel on the change above first.'], 422);
        }

        $text = trim($data['message']);

        return $this->respond($request, $conversation, $text, fn () => $this->assistant->send($request->user(), $conversation, $text));
    }

    public function decide(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string'], 'approve' => ['required', 'boolean']]);
        $conversation = $this->conversation($request);

        if (($conversation->pending['id'] ?? null) !== $data['id']) {
            return response()->json(['message' => 'This change is no longer waiting. Please ask again.'], 409);
        }

        $note = ($data['approve'] ? 'Confirmed: ' : 'Cancelled: ').$conversation->pending['summary'];

        return $this->respond($request, $conversation, $note, fn () => $this->assistant->decide($request->user(), $conversation, (bool) $data['approve']));
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assistant');
    }

    /**
     * Runs one turn and saves the chat. If the AI cannot be reached nothing is
     * saved, so the user can simply send the same message again.
     *
     * @param  Closure(): Turn  $work
     */
    private function respond(Request $request, Conversation $conversation, string $userText, Closure $work): JsonResponse
    {
        if (! $this->assistant->enabled()) {
            return response()->json(['message' => 'Ask AI is switched off on this server.'], 503);
        }

        // One turn at a time per user, so a double tap cannot confirm a change twice
        $lock = Cache::lock('assistant:'.$request->user()->id, 120);
        if (! $lock->get()) {
            return response()->json(['message' => 'Still working on your last message.'], 429);
        }

        try {
            $turn = $work();
        } catch (AssistantUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } finally {
            $lock->release();
        }

        $conversation->messages[] = ['role' => 'user', 'text' => $userText];
        $conversation->messages[] = ['role' => 'assistant', ...Arr::except($turn->toArray(), 'pending')];
        $conversation->trim();
        $request->session()->put(self::SESSION_KEY, $conversation->toArray());

        return response()->json($turn->toArray());
    }

    private function conversation(Request $request): Conversation
    {
        return Conversation::fromArray($request->session()->get(self::SESSION_KEY, []));
    }

    /**
     * @return list<string>
     */
    private function suggestions(User $user): array
    {
        if ($user->isManager()) {
            return [
                'Give me today\'s hall summary',
                'Which seats are vacant?',
                'Who has overdue bills?',
                'How many dinners tomorrow?',
            ];
        }

        if ($user->isResident()) {
            return [
                'How much do I have to pay?',
                'Turn off my meals tomorrow',
                'Can I cook in my room?',
                'What happened to my complaint?',
            ];
        }

        return [
            'How do I apply for a seat?',
            'What documents do I need?',
            'What is the status of my application?',
            'What are the main hall rules?',
        ];
    }
}
