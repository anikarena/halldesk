<x-layouts.app :title="$notice->exists ? 'Edit notice' : 'New notice'">
    <x-page-header :title="$notice->exists ? 'Edit notice' : 'New notice'" :back="route('manage.notices.index')" back-label="Notices" />

    <form method="POST" action="{{ $notice->exists ? route('manage.notices.update', $notice) : route('manage.notices.store') }}" class="card max-w-4xl">
        @csrf
        @if ($notice->exists) @method('PUT') @endif
        <div class="card-body space-y-5">
            <x-input name="title" label="Title" required maxlength="150" :value="$notice->title" />
            <x-textarea name="body" label="Notice" required rows="8" :value="$notice->body" hint="Plain text. Empty lines start a new paragraph." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="category" label="Category" required :value="$notice->category" :options="$categories" />
                <x-select name="priority" label="Priority" required :value="$notice->priority" :options="$priorities"
                          hint="Urgent: red banner on every page. Important: highlighted on the board." />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="published_at" type="datetime-local" label="Publish at" :value="$notice->published_at?->format('Y-m-d\TH:i')" hint="Empty means publish now. A future time schedules it." />
                <x-input name="expires_at" type="datetime-local" label="Hide after" :value="$notice->expires_at?->format('Y-m-d\TH:i')" hint="Optional. Empty means it stays." />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-checkbox name="is_pinned" label="Pin to the top" :checked="$notice->is_pinned" hint="Pinned notices come before all others." />
                <x-checkbox name="is_public" label="Show on the public home page" :checked="$notice->is_public" hint="Visible to anyone, without signing in." />
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-5 py-4">
            @if ($notice->exists)
                <button type="submit" form="delete-notice" class="btn btn-soft-danger mr-auto"><x-icon name="trash" class="size-4" /> Delete</button>
            @endif
            <a href="{{ route('manage.notices.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $notice->exists ? 'Save notice' : 'Publish notice' }}</button>
        </div>
    </form>

    @if ($notice->exists)
        <form id="delete-notice" method="POST" action="{{ route('manage.notices.destroy', $notice) }}" onsubmit="return confirm('Delete this notice for everyone?')">
            @csrf @method('DELETE')
        </form>
    @endif
</x-layouts.app>
