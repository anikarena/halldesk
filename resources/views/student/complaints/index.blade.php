<x-layouts.app title="Complaints">
    <x-page-header title="Complaints" description="Report anything broken or wrong in your room or the hall, and follow it until it is fixed.">
        @if ($isResident)
            <x-slot:actions>
                <a href="{{ route('student.complaints.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New complaint</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="card">
        @if ($complaints->isEmpty())
            <x-empty icon="wrench" title="No complaints yet">
                @if ($isResident)
                    If something in your room needs repair, <a href="{{ route('student.complaints.create') }}" class="link">tell the hall office</a>.
                @else
                    Complaints can be submitted once you have a seat.
                @endif
            </x-empty>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($complaints as $complaint)
                    <li>
                        <a href="{{ route('student.complaints.show', $complaint) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-slate-50">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-900">{{ $complaint->title }}</span>
                                    <x-status :value="$complaint->priority" />
                                </span>
                                <span class="mt-1 block text-sm text-slate-500">
                                    {{ $complaint->reference() }}, {{ $complaint->category->label() }}, {{ $complaint->created_at->format('j M Y') }}{{ $complaint->assignee ? ', handled by '.$complaint->assignee->name : '' }}
                                </span>
                            </span>
                            <x-status :value="$complaint->status" />
                            <x-icon name="chevron-right" class="size-4 text-slate-300" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-5 py-3">{{ $complaints->links() }}</div>
        @endif
    </div>
</x-layouts.app>
