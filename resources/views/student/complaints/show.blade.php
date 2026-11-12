<x-layouts.app :title="$complaint->reference()">
    <x-page-header :title="$complaint->title" :back="route('student.complaints.index')" back-label="Complaints"
                   :description="$complaint->reference().', '.$complaint->category->label().', room '.($complaint->room?->number ?? 'n/a')">
        <x-slot:actions>
            <x-status :value="$complaint->priority" />
            <x-status :value="$complaint->status" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Timeline</h2></div>
            <div class="card-body">
                @include('partials.complaint-timeline')
            </div>

            <div class="border-t border-slate-100 p-5">
                @if ($complaint->isOpen())
                    <form method="POST" action="{{ route('student.complaints.comment', $complaint) }}" class="space-y-3">
                        @csrf
                        <x-textarea name="body" label="Add a comment" rows="3" required placeholder="Any update for the hall office?" />
                        <div class="flex justify-end"><button type="submit" class="btn btn-primary">Send comment</button></div>
                    </form>
                @else
                    <form method="POST" action="{{ route('student.complaints.reopen', $complaint) }}" class="space-y-3">
                        @csrf
                        <x-textarea name="body" label="Still not fixed?" rows="3" required placeholder="Tell the office what is still wrong, the complaint will open again." />
                        <div class="flex justify-end"><button type="submit" class="btn btn-secondary"><x-icon name="rotate" class="size-4" /> Reopen complaint</button></div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card h-fit">
            <div class="card-header"><h2 class="card-title">Summary</h2></div>
            <dl class="divide-y divide-slate-100 px-5">
                <div class="dl-row"><dt>Reference</dt><dd>{{ $complaint->reference() }}</dd></div>
                <div class="dl-row"><dt>Submitted</dt><dd>{{ $complaint->created_at->format('j M Y') }}</dd></div>
                <div class="dl-row"><dt>Handled by</dt><dd>{{ $complaint->assignee?->name ?? 'Not assigned yet' }}</dd></div>
                @if ($complaint->resolved_at)
                    <div class="dl-row"><dt>Resolved</dt><dd>{{ $complaint->resolved_at->format('j M Y, g:i A') }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</x-layouts.app>
