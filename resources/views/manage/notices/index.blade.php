<x-layouts.app title="Manage notices">
    <x-page-header title="Manage notices" description="Urgent notices show as a red banner on every page until they expire.">
        <x-slot:actions>
            <a href="{{ route('notices.index') }}" class="btn btn-secondary"><x-icon name="eye" class="size-4" /> View board</a>
            <a href="{{ route('manage.notices.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New notice</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        @if ($notices->isEmpty())
            <x-empty icon="megaphone" title="No notices yet" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Title</th><th>Category</th><th>Priority</th><th>Visible</th><th>Published</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($notices as $notice)
                            <tr>
                                <td>
                                    <a href="{{ route('notices.show', $notice) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $notice->title }}</a>
                                    <span class="mt-0.5 flex gap-1.5">
                                        @if ($notice->is_pinned)<x-badge tone="violet">Pinned</x-badge>@endif
                                        @if ($notice->is_public)<x-badge tone="blue">Public</x-badge>@endif
                                    </span>
                                </td>
                                <td>{{ $notice->category->label() }}</td>
                                <td><x-status :value="$notice->priority" /></td>
                                <td>
                                    @if ($notice->isLive())
                                        <x-badge tone="green" dot>Live</x-badge>
                                    @elseif ($notice->published_at->isFuture())
                                        <x-badge tone="amber" dot>Scheduled</x-badge>
                                    @else
                                        <x-badge dot>Expired</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-slate-500">{{ $notice->published_at->format('j M Y, g:i A') }}</td>
                                <td class="whitespace-nowrap text-slate-500">{{ $notice->expires_at?->format('j M Y') ?? 'Never' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('manage.notices.edit', $notice) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-3.5" /> Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $notices->links() }}</div>
        @endif
    </div>
</x-layouts.app>
