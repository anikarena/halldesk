{{-- Timeline of a complaint: the original report, then every comment and status change --}}
<ol class="relative space-y-6 border-l border-slate-200 pl-6">
    <li class="relative">
        <span class="absolute -left-[31px] grid size-4 place-items-center rounded-full bg-white ring-4 ring-white"><span class="size-2.5 rounded-full bg-slate-400"></span></span>
        <p class="text-sm"><span class="font-semibold text-slate-800">{{ $complaint->user->name }}</span> <span class="text-slate-500">reported this</span></p>
        <p class="text-xs text-slate-400">{{ $complaint->created_at->format('j M Y, g:i A') }}</p>
        <div class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 p-3.5 text-sm text-slate-700">{{ $complaint->description }}</div>
    </li>
    @foreach ($complaint->updates as $update)
        <li class="relative">
            <span class="absolute -left-[31px] grid size-4 place-items-center rounded-full bg-white ring-4 ring-white">
                <span @class(['size-2.5 rounded-full', 'bg-brand-500' => $update->user->isManager(), 'bg-sky-500' => ! $update->user->isManager()])></span>
            </span>
            <p class="text-sm">
                <span class="font-semibold text-slate-800">{{ $update->user->name }}</span>
                @if ($update->user->isManager()) <x-badge tone="brand" class="ml-1">{{ $update->user->role->label() }}</x-badge> @endif
                @if ($update->to_status)
                    <span class="text-slate-500">changed the status to</span> <x-status :value="$update->to_status" />
                @else
                    <span class="text-slate-500">commented</span>
                @endif
            </p>
            <p class="text-xs text-slate-400">{{ $update->created_at->format('j M Y, g:i A') }}</p>
            @if ($update->body)
                <div class="mt-2 whitespace-pre-line rounded-lg border border-slate-200 p-3.5 text-sm text-slate-700">{{ $update->body }}</div>
            @endif
        </li>
    @endforeach
</ol>
