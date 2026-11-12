<x-layouts.app :title="$complaint->reference()">
    <x-page-header :title="$complaint->title" :back="route('manage.complaints.index')" back-label="Complaints"
                   :description="$complaint->reference().', '.$complaint->category->label().', room '.($complaint->room?->number ?? 'n/a').', '.$complaint->created_at->format('j M Y, g:i A')">
        <x-slot:actions>
            <x-status :value="$complaint->priority" />
            <x-status :value="$complaint->status" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Timeline</h2></div>
            <div class="card-body">@include('partials.complaint-timeline')</div>
            <form method="POST" action="{{ route('manage.complaints.comment', $complaint) }}" class="space-y-3 border-t border-slate-100 p-5">
                @csrf
                <x-textarea name="body" label="Reply to the student" rows="3" required placeholder="The student gets a notification." />
                <div class="flex justify-end"><button type="submit" class="btn btn-secondary"><x-icon name="message" class="size-4" /> Send reply</button></div>
            </form>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('manage.complaints.update', $complaint) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><h2 class="card-title">Manage</h2></div>
                <div class="card-body space-y-4">
                    <x-select name="status" label="Status" required :value="$complaint->status" :options="\App\Enums\ComplaintStatus::options()" />
                    <x-select name="priority" label="Priority" required :value="$complaint->priority" :options="\App\Enums\ComplaintPriority::options()" />
                    <x-select name="assigned_to" label="Assigned to" placeholder="Nobody yet" :value="$complaint->assigned_to"
                              :options="$staff->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->id === auth()->id() ? ' (me)' : '')])->all()" />
                    <x-textarea name="note" label="Note (optional)" rows="2" placeholder="Shown in the timeline, for example: electrician will come at 3 PM" />
                </div>
                <div class="border-t border-slate-100 px-5 py-4"><button type="submit" class="btn btn-primary w-full">Save</button></div>
            </form>

            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <x-avatar :user="$complaint->user" />
                    <div class="min-w-0">
                        <a href="{{ route('manage.students.show', $complaint->user) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-700">{{ $complaint->user->name }}</a>
                        <p class="text-xs text-slate-500">{{ $complaint->user->profile?->student_id }}, {{ $complaint->user->phone }}</p>
                    </div>
                </div>
                @if ($complaint->room)
                    <a href="{{ route('manage.rooms.show', $complaint->room) }}" class="btn btn-secondary btn-sm mt-4 w-full">Open room {{ $complaint->room->number }}</a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
