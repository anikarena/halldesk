<x-layouts.app title="My visitors">
    <x-page-header title="My visitors" description="Everyone the gate recorded as visiting you." />

    <div class="card">
        @if ($visitors->isEmpty())
            <x-empty icon="door" title="No visitors recorded">When someone visits you, the gate staff record it here.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Visitor</th><th>Relation</th><th>Purpose</th><th>Checked in</th><th>Checked out</th></tr></thead>
                    <tbody>
                        @foreach ($visitors as $visitor)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $visitor->visitor_name }}</td>
                                <td>{{ $visitor->relation }}</td>
                                <td class="text-slate-500">{{ $visitor->purpose ?: 'Not given' }}</td>
                                <td>{{ $visitor->check_in_at->format('j M Y, g:i A') }}</td>
                                <td>
                                    @if ($visitor->isInside())
                                        <x-badge tone="amber" dot>Inside now</x-badge>
                                    @else
                                        {{ $visitor->check_out_at->format('g:i A') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $visitors->links() }}</div>
        @endif
    </div>
</x-layouts.app>
