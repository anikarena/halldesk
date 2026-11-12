<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" description="Decisions, bills, payments and complaint updates for you.">
        @if (auth()->user()->unreadNotifications()->exists())
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary"><x-icon name="check" class="size-4" /> Mark all as read</button>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    @php
        $tones = [
            'success' => ['bg-emerald-50 text-emerald-700', 'check-circle'],
            'warning' => ['bg-amber-50 text-amber-700', 'alert'],
            'danger' => ['bg-rose-50 text-rose-700', 'alert-circle'],
            'info' => ['bg-sky-50 text-sky-700', 'info'],
        ];
    @endphp

    @include('partials.push-alerts')

    <div class="card max-w-3xl">
        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                @php [$tone, $icon] = $tones[$notification->data['tone'] ?? 'info'] ?? $tones['info']; @endphp
                <li>
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex gap-4 px-5 py-4 hover:bg-slate-50', 'bg-brand-50/40' => ! $notification->read_at])>
                        <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $tone }}"><x-icon :name="$icon" class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span @class(['text-sm text-slate-900', 'font-semibold' => ! $notification->read_at, 'font-medium' => $notification->read_at])>{{ $notification->data['title'] ?? 'Notification' }}</span>
                                @unless ($notification->read_at)<span class="size-2 rounded-full bg-brand-500" title="Unread"></span>@endunless
                            </span>
                            <span class="mt-0.5 block text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                </li>
            @empty
                <li><x-empty icon="bell" title="No notifications yet">You will see updates about your seat, bills and complaints here.</x-empty></li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
