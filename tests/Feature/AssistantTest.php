<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\MealOff;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\Assistant\Gemini;
use App\Services\Assistant\Knowledge;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

/**
 * Ask AI with the Gemini API faked: the tests play the model's part and check
 * what the app does with each tool call.
 */
class AssistantTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private const CHAT = 'generativelanguage.googleapis.com/*:generateContent';

    private const EMBED = 'generativelanguage.googleapis.com/*:batchEmbedContents';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hall.ai.key' => 'test-key']);
        // Thursday 5 November 2026, 3 PM
        $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00'));
    }

    public function test_guests_cannot_use_ask_ai(): void
    {
        $this->get(route('assistant'))->assertRedirect(route('login'));
        $this->postJson(route('assistant.message'), ['message' => 'hi'])->assertUnauthorized();
    }

    public function test_the_page_says_when_ask_ai_is_switched_off(): void
    {
        config(['hall.ai.key' => null]);
        $student = $this->student();

        $this->actingAs($student)->get(route('assistant'))->assertOk()->assertSee('Ask AI is switched off');
        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'hi'])->assertStatus(503);
    }

    public function test_an_answer_is_returned_and_kept_in_the_chat(): void
    {
        Http::fake([self::CHAT => Http::response($this->says('I can help with your meals, bills and complaints.'))]);
        $student = $this->student();

        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'hello'])
            ->assertOk()
            ->assertJson(['text' => 'I can help with your meals, bills and complaints.', 'pending' => null]);

        $this->actingAs($student)->get(route('assistant'))->assertSee('I can help with your meals, bills and complaints.');
    }

    public function test_dashes_and_markdown_are_tidied_out_of_answers(): void
    {
        $reply = $this->says("**Rent** is due on the 10th — pay early.\n- bKash\n- Na");
        $reply['candidates'][0]['content']['parts'][] = ['text' => 'gad']; // one answer split over two parts
        Http::fake([self::CHAT => Http::response($reply)]);

        $this->actingAs($this->student())->postJson(route('assistant.message'), ['message' => 'rent?'])
            ->assertJsonPath('text', "Rent is due on the 10th, pay early.\n• bKash\n• Nagad");
    }

    public function test_a_tool_result_goes_back_to_the_model(): void
    {
        $student = $this->resident();
        $this->bill($student, 3500, '2026-11-10');
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('my_bills'))
            ->push($this->says('You have ৳3,500 to pay by Tue, 10 Nov.'))]);

        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'koto taka baki?'])
            ->assertOk()
            ->assertJsonPath('text', 'You have ৳3,500 to pay by Tue, 10 Nov.')
            ->assertJsonPath('links.0.url', route('student.bills.index'));

        $result = $this->lastToolResult(1);
        $this->assertSame('৳3,500', $result['total_due']);
        // A call without arguments goes back as "args": {}, Gemini refuses "args": []
        $second = Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), 'generateContent'))[1][0];
        $this->assertStringContainsString('"args":{}', $second->body());
        $this->assertTrue(collect($this->chatRequests()[0]['contents'][0]['parts'])->contains('text', 'koto taka baki?'));
    }

    public function test_empty_tool_arguments_from_the_session_are_sent_as_an_object(): void
    {
        // The JSON session turns "args": {} into [] between two messages
        Http::fake([self::CHAT => Http::response($this->says('ok'))]);

        app(Gemini::class)->generate('system', [
            ['role' => 'user', 'parts' => [['text' => 'koto taka baki?']]],
            ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'my_bills', 'args' => []]]]],
            ['role' => 'user', 'parts' => [['functionResponse' => ['name' => 'my_bills', 'response' => ['result' => []]]]]],
            ['role' => 'model', 'parts' => [['text' => 'You owe nothing.']]],
            ['role' => 'user', 'parts' => [['text' => 'thanks']]],
        ], []);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->body(), '"functionCall":{"name":"my_bills","args":{}}'));
    }

    public function test_turning_meals_off_waits_for_confirm(): void
    {
        $student = $this->resident();
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('set_meals', ['from' => '2026-11-07', 'to' => '2026-11-09', 'meals' => ['lunch', 'dinner'], 'on' => false]))
            ->push($this->says('Done, your meals are off from Sat to Mon.'))]);

        $pending = $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'shonibar theke 3 din meal off'])
            ->assertOk()
            ->assertJsonPath('pending.summary', 'Lunch and dinner OFF from Sat, 7 Nov to Mon, 9 Nov')
            ->json('pending');
        $this->assertSame(0, MealOff::query()->count());

        // Nothing else can be asked while the change waits
        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'hello'])->assertStatus(422);

        $this->actingAs($student)->postJson(route('assistant.decide'), ['id' => $pending['id'], 'approve' => true])
            ->assertOk()
            ->assertJsonPath('text', 'Done, your meals are off from Sat to Mon.');
        $this->assertSame(6, MealOff::query()->where('user_id', $student->id)->count());
        $this->assertSame(3, $this->lastToolResult(1)['days_changed']);
        $this->assertTrue($this->lastToolResult(1)['confirmed_by_user']);

        // The same card cannot be confirmed twice
        $this->actingAs($student)->postJson(route('assistant.decide'), ['id' => $pending['id'], 'approve' => true])->assertStatus(409);
    }

    public function test_cancel_changes_nothing(): void
    {
        $student = $this->resident();
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('set_meals', ['from' => '2026-11-07', 'to' => '2026-11-07', 'meals' => ['dinner'], 'on' => false]))
            ->push($this->says('Okay, I left your meals as they are.'))]);

        $pending = $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'dinner off on Saturday'])->json('pending');
        $this->actingAs($student)->postJson(route('assistant.decide'), ['id' => $pending['id'], 'approve' => false])->assertOk();

        $this->assertSame(0, MealOff::query()->count());
        $this->assertTrue($this->lastToolResult(1)['cancelled']);
    }

    public function test_a_locked_date_is_refused_before_the_confirm_card(): void
    {
        $student = $this->resident();
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('set_meals', ['from' => '2026-11-05', 'to' => '2026-11-05', 'meals' => ['dinner'], 'on' => false]))
            ->push($this->says('Sorry, today\'s meals are already locked.'))]);

        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'aj raate dinner off'])
            ->assertOk()
            ->assertJsonPath('pending', null);

        $this->assertStringContainsString('locked', $this->lastToolResult(1)['error']);
    }

    public function test_students_never_get_the_office_tools(): void
    {
        $student = $this->resident();
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('dues_list'))
            ->push($this->says('I can only help with your own account.'))]);

        $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'who owes money?'])->assertOk();

        $offered = array_column($this->chatRequests()[0]['tools'][0]['functionDeclarations'], 'name');
        $this->assertContains('set_meals', $offered);
        $this->assertNotContains('dues_list', $offered);
        $this->assertNotContains('remind_dues', $offered);
        // Asking for it anyway only gives an error
        $this->assertArrayHasKey('error', $this->lastToolResult(1));
    }

    public function test_a_complaint_is_submitted_after_confirm(): void
    {
        $student = $this->resident();
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('create_complaint', [
                'category' => 'electricity',
                'title' => 'Ceiling fan not working',
                'description' => 'The ceiling fan in my room stopped working last night.',
                'priority' => 'medium',
            ]))
            ->push($this->says('Your complaint C-0001 is submitted.'))]);

        $pending = $this->actingAs($student)->postJson(route('assistant.message'), ['message' => 'amar room er fan noshto'])
            ->assertJsonPath('pending.summary', 'Submit a complaint: "Ceiling fan not working" (Electricity, medium priority)')
            ->json('pending');
        $this->assertSame(0, Complaint::query()->count());

        $this->actingAs($student)->postJson(route('assistant.decide'), ['id' => $pending['id'], 'approve' => true])->assertOk();

        $complaint = Complaint::query()->sole();
        $this->assertSame($student->id, $complaint->user_id);
        $this->assertSame($complaint->reference(), $this->lastToolResult(1)['reference']);
    }

    public function test_the_office_can_send_bill_reminders(): void
    {
        $student = $this->resident();
        $this->bill($student, 3500, '2026-11-01');
        Http::fake([self::CHAT => Http::sequence()
            ->push($this->calls('remind_dues', ['overdue_only' => true]))
            ->push($this->says('Reminder sent to 1 student.'))]);
        $admin = $this->admin();
        // The seat allotment already sent the student one notification
        $before = $student->notifications()->count();

        $pending = $this->actingAs($admin)->postJson(route('assistant.message'), ['message' => 'remind everyone with overdue bills'])
            ->assertJsonPath('pending.summary', 'Send a bill reminder to 1 student with overdue bills (৳3,500 in total)')
            ->json('pending');
        $this->assertSame($before, $student->notifications()->count());

        $this->actingAs($admin)->postJson(route('assistant.decide'), ['id' => $pending['id'], 'approve' => true])->assertOk();
        $this->assertSame($before + 1, $student->notifications()->count());
        $this->assertContains('Bill reminder', $student->notifications()->get()->pluck('data.title'));
    }

    public function test_rule_search_returns_the_closest_section(): void
    {
        Http::fake([
            self::EMBED => fn (HttpRequest $request) => Http::response(['embeddings' => array_map(
                fn (array $item) => ['values' => $this->fakeVector($item['content']['parts'][0]['text'])],
                $request->data()['requests'],
            )]),
            self::CHAT => Http::sequence()
                ->push($this->calls('search_hall_rules', ['query' => 'cooking in room']))
                ->push($this->says('No, cooking is not allowed in the hall.')),
        ]);

        $this->actingAs($this->student())->postJson(route('assistant.message'), ['message' => 'Can I cook in my room?'])
            ->assertOk()
            ->assertJsonPath('links.0.url', route('rules').'#6-cooking-and-electric-appliances');

        $this->assertSame('Hall rules, 6. Cooking and electric appliances', $this->lastToolResult(1)['passages'][0]['title']);
        $this->assertDatabaseCount('knowledge_chunks', 15);

        // Passages that did not change are not embedded again
        $embedCalls = fn () => Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), 'batchEmbedContents'))->count();
        $before = $embedCalls();
        app(Knowledge::class)->sync();
        $this->assertSame($before, $embedCalls());
    }

    public function test_rule_search_falls_back_to_keywords_without_embeddings(): void
    {
        Sleep::fake();
        Http::fake([
            self::EMBED => Http::response(['error' => ['message' => 'down']], 500),
            self::CHAT => Http::sequence()
                ->push($this->calls('search_hall_rules', ['query' => 'ragging']))
                ->push($this->says('Ragging of any type is not allowed.')),
        ]);

        $this->actingAs($this->student())->postJson(route('assistant.message'), ['message' => 'ragging er niyom ki?'])->assertOk();

        $this->assertSame('Hall rules, 3. Ragging', $this->lastToolResult(1)['passages'][0]['title']);
    }

    public function test_the_rules_page_fills_in_hall_settings(): void
    {
        $this->actingAs($this->student())->get(route('rules'))
            ->assertOk()
            ->assertSee('Cooking is not allowed in the hall.')
            ->assertSee('Changes for a date close at 10:00 PM on the day before.')
            ->assertSee('Lunch costs ৳70 and dinner costs ৳70.')
            ->assertSee('between the 1st and the 10th of the month')
            ->assertDontSee('{lunch_rate}')
            ->assertDontSee('Names in curly brackets');
    }

    /**
     * @return array<string, mixed>
     */
    private function says(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function calls(string $tool, array $args = []): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => $tool, 'args' => (object) $args]]]]]]];
    }

    /**
     * Bodies of the chat requests sent to Gemini, in order.
     *
     * @return list<array<string, mixed>>
     */
    private function chatRequests(): array
    {
        return Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), 'generateContent'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    /**
     * The tool result the app sent back in chat request number $index.
     *
     * @return array<string, mixed>
     */
    private function lastToolResult(int $index): array
    {
        $contents = $this->chatRequests()[$index]['contents'];

        return end($contents)['parts'][0]['functionResponse']['response']['result'];
    }

    /**
     * A tiny fake embedding: one dimension per topic word.
     *
     * @return list<float>
     */
    private function fakeVector(string $text): array
    {
        $text = strtolower($text);

        return array_map(fn (string $word) => str_contains($text, $word) ? 1.0 : 0.0, ['cook', 'rent', 'meal', 'ragging'])
            + [4 => 0.1];
    }

    private function resident(): User
    {
        $student = $this->student();
        app(AllocationService::class)->allocate($student, $this->seat($this->room()), $this->admin(), CarbonImmutable::parse('2026-10-01'));

        return $student;
    }

    private function bill(User $student, int $total, string $due): Invoice
    {
        return Invoice::query()->create([
            'user_id' => $student->id,
            'kind' => Invoice::KIND_OTHER,
            'title' => 'Hall fee',
            'due_date' => $due,
            'total' => $total,
            'status' => InvoiceStatus::Unpaid,
        ]);
    }
}
