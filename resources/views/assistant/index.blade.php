<x-layouts.app title="Ask AI">
    <div class="mx-auto max-w-3xl"
         x-data="assistantChat(@js([
             'messages' => $messages,
             'pending' => $pending,
             'urls' => ['message' => route('assistant.message'), 'decide' => route('assistant.decide')],
         ]))">
        <x-page-header title="Ask AI" description="Ask about your meals, bills, complaints and the hall rules. It only sees your own account.">
            @if ($enabled)
                <x-slot:actions>
                    <form method="POST" action="{{ route('assistant.reset') }}" x-show="messages.length" x-cloak>
                        @csrf
                        <button type="submit" class="btn btn-secondary"><x-icon name="rotate" class="size-4" /> New chat</button>
                    </form>
                </x-slot:actions>
            @endif
        </x-page-header>

        @unless ($enabled)
            <div class="card">
                <x-empty icon="sparkle" title="Ask AI is switched off">
                    @if (auth()->user()->isAdmin())
                        Add GEMINI_API_KEY to the server's .env file to switch it on.
                    @else
                        Please use the menu for now, or contact the hall office.
                    @endif
                </x-empty>
            </div>
        @else
            {{-- First visit: a greeting and a few questions to tap --}}
            <div x-show="!messages.length && !busy" class="card p-6 sm:p-8">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="sparkle" /></span>
                <p class="display mt-5 text-3xl">Hi {{ auth()->user()->firstName() }}, how can I help?</p>
                <p class="mt-2 text-sm text-slate-500">Write in English, Bangla or Banglish. Changes like turning meals off wait for your OK.</p>
                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    @foreach ($suggestions as $suggestion)
                        <button type="button" class="rounded-xl bg-cream px-4 py-3 text-left text-sm text-slate-700 ring-1 ring-slate-200/70 transition hover:ring-brand-300"
                                x-on:click="send(@js($suggestion))">{{ $suggestion }}</button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <template x-for="(message, index) in messages" :key="index">
                    <div x-show="message.text" class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div :class="message.role === 'user'
                                ? 'max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-2.5 text-white'
                                : 'max-w-[92%] rounded-2xl rounded-bl-md bg-cream px-4 py-3 text-slate-800 ring-1 ring-slate-200/60'">
                            <p class="whitespace-pre-line text-[15px] leading-relaxed" x-text="message.text"></p>
                            <div x-show="message.links && message.links.length" class="mt-3 flex flex-wrap gap-1.5">
                                <template x-for="link in (message.links || [])" :key="link.url">
                                    <a :href="link.url" class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-brand-700 ring-1 ring-brand-100 hover:bg-brand-50">
                                        <span x-text="link.label"></span><x-icon name="chevron-right" class="size-3" />
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- A change the assistant wants to make: nothing happens until Confirm --}}
                <div x-show="pending" x-cloak class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-brand-600">Please confirm</p>
                    <p class="mt-1.5 font-medium text-slate-900" x-text="pending && pending.summary"></p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn btn-primary" :disabled="busy" x-on:click="decide(true)"><x-icon name="check" class="size-4" /> Confirm</button>
                        <button type="button" class="btn btn-secondary" :disabled="busy" x-on:click="decide(false)">Cancel</button>
                    </div>
                </div>

                <div x-show="busy" x-cloak class="flex justify-start">
                    <div class="rounded-2xl rounded-bl-md bg-cream px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-200/60">
                        <span class="typing-dots" aria-label="Thinking"><span></span><span></span><span></span></span>
                    </div>
                </div>

                <p x-show="error" x-cloak class="rounded-xl bg-rose-50 px-4 py-2.5 text-sm text-rose-700" x-text="error"></p>
            </div>

            <form x-on:submit.prevent="send()" class="no-print sticky bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 mt-6 lg:bottom-6">
                <div class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg">
                    <label for="ask" class="sr-only">Your question</label>
                    <textarea id="ask" x-ref="input" x-model="draft" rows="1" maxlength="1000"
                              class="max-h-40 min-w-0 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                              placeholder="Ask about meals, bills or rules"
                              :disabled="!!pending"
                              x-on:input="grow()"
                              x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send() }"></textarea>
                    <button type="submit" class="btn btn-primary size-10 shrink-0 rounded-xl p-0" :disabled="busy || pending || ! draft.trim()" aria-label="Send">
                        <x-icon name="arrow-right" class="size-5" />
                    </button>
                </div>
                <p class="mt-1.5 px-2 text-[11px] text-slate-400">AI can make mistakes. Check important details on the Bills and Meals pages.</p>
            </form>
        @endunless
    </div>
</x-layouts.app>
