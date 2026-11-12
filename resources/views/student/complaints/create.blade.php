<x-layouts.app title="New complaint">
    <x-page-header title="New complaint" :back="route('student.complaints.index')" back-label="Complaints"
                   description="Your room number is added automatically. Be specific so the right person can fix it fast." />

    <form method="POST" action="{{ route('student.complaints.store') }}" class="card max-w-3xl">
        @csrf
        <div class="card-body space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="category" label="What is it about?" required :options="$categories" placeholder="Choose a category" />
                <x-select name="priority" label="How urgent is it?" required :options="$priorities" value="medium"
                          hint="High: unsafe or blocks daily life, like no water or a sparking switch." />
            </div>
            <x-input name="title" label="Short title" required maxlength="120" placeholder="e.g. Ceiling fan not working" />
            <x-textarea name="description" label="Details" required rows="5" placeholder="What happened, since when, and where exactly." />
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('student.complaints.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Submit complaint</button>
        </div>
    </form>
</x-layouts.app>
