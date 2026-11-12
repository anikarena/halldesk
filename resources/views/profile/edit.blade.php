<x-layouts.app title="My profile">
    <x-page-header title="My profile" description="Keep your contact details up to date so the hall office can reach you." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('profile.update') }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><h2 class="card-title">Personal details</h2></div>
                <div class="card-body space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="name" label="Full name" required :value="$user->name" />
                        <x-input name="phone" label="Mobile number" :value="$user->phone" placeholder="01712 345678" />
                    </div>
                    <x-input name="email" type="email" label="Email address" required :value="$user->email" />

                    @if ($user->isStudent())
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-select name="gender" label="Gender" placeholder="Not given" :options="['male' => 'Male', 'female' => 'Female']" :value="$user->profile?->gender" />
                            <x-input name="date_of_birth" type="date" label="Date of birth" :value="$user->profile?->date_of_birth?->toDateString()" />
                            <x-select name="blood_group" label="Blood group" placeholder="Not given" :options="array_combine($bloodGroups, $bloodGroups)" :value="$user->profile?->blood_group" />
                        </div>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-input name="home_district" label="Home district" :value="$user->profile?->home_district" />
                            <div class="sm:col-span-2"><x-input name="permanent_address" label="Permanent address" :value="$user->profile?->permanent_address" /></div>
                        </div>
                        <div class="border-t border-slate-100 pt-5">
                            <p class="mb-4 text-sm font-semibold text-slate-800">Guardian (for emergencies)</p>
                            <div class="grid gap-5 sm:grid-cols-3">
                                <x-input name="guardian_name" label="Name" :value="$user->profile?->guardian_name" />
                                <x-input name="guardian_relation" label="Relation" :value="$user->profile?->guardian_relation" placeholder="Father, mother..." />
                                <x-input name="guardian_phone" label="Phone" :value="$user->profile?->guardian_phone" />
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><h2 class="card-title">Change password</h2></div>
                <div class="card-body grid gap-5 sm:grid-cols-3">
                    <x-input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                    <x-input name="password" type="password" label="New password" required autocomplete="new-password" hint="At least 8 characters." />
                    <x-input name="password_confirmation" type="password" label="Repeat new password" required autocomplete="new-password" />
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                    <button type="submit" class="btn btn-secondary"><x-icon name="key" class="size-4" /> Update password</button>
                </div>
            </form>

            @if ($user->isManager())
                @php
                    $lunchAt = \Carbon\CarbonImmutable::createFromFormat('H:i', config('hall.telegram.lunch_at'))->format('g:i A');
                    $dinnerAt = \Carbon\CarbonImmutable::createFromFormat('H:i', config('hall.telegram.dinner_at'))->format('g:i A');
                @endphp
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Telegram</h2></div>
                    <div class="card-body flex flex-wrap items-center justify-between gap-4">
                        <div class="max-w-lg text-sm text-slate-600">
                            @if ($user->telegram_chat_id)
                                <p class="mb-1 flex items-center gap-1.5 font-medium text-emerald-700"><x-icon name="check-circle" class="size-4" /> Connected</p>
                                <p>The HallDesk bot sends you the lunch list at {{ $lunchAt }} and the dinner list at {{ $dinnerAt }}.
                                    @if ($user->isAdmin()) You can also write to the bot to send a notification to every resident. @endif</p>
                            @else
                                <p>Get the lunch list at {{ $lunchAt }} and the dinner list at {{ $dinnerAt }} on Telegram: how many residents eat and who is off.
                                    @if ($user->isAdmin()) As provost you can also notify every resident from there. @endif</p>
                            @endif
                        </div>
                        @if (! $telegramReady)
                            <p class="text-sm text-slate-400">The bot is not set up on this server.</p>
                        @elseif ($user->telegram_chat_id)
                            <form method="POST" action="{{ route('profile.telegram.disconnect') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-secondary">Disconnect</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('profile.telegram') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary"><x-icon name="message" class="size-4" /> Connect Telegram</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="card h-fit p-6 text-center">
            <x-avatar :user="$user" size="xl" class="mx-auto" />
            <p class="mt-3 text-lg font-semibold text-slate-900">{{ $user->name }}</p>
            <p class="text-sm text-slate-500">{{ $user->role->label() }}</p>
            @if ($user->profile)
                <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 text-left">
                    <div class="dl-row"><dt>Student ID</dt><dd>{{ $user->profile->student_id }}</dd></div>
                    <div class="dl-row"><dt>Department</dt><dd>{{ $user->profile->department }}</dd></div>
                    <div class="dl-row"><dt>Year</dt><dd>{{ $user->profile->yearLabel() }}</dd></div>
                    <div class="dl-row"><dt>CGPA</dt><dd>{{ $user->profile->cgpa ?? 'Not given' }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">Academic details are official records. Ask the hall office to correct them.</p>
            @endif
        </div>
    </div>
</x-layouts.app>
