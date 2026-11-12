@if (session('success') || session('error') || session('info') || $errors->any())
    <div class="mb-6 space-y-3">
        @if (session('success'))
            <x-alert tone="success" x-data="{ show: true }" x-show="show">
                {{ session('success') }}
                <x-slot:actions>
                    <button type="button" class="-m-1 rounded p-1 opacity-60 hover:opacity-100" x-on:click="show = false" aria-label="Close"><x-icon name="x" class="size-4" /></button>
                </x-slot:actions>
            </x-alert>
        @endif
        @if (session('error'))
            <x-alert tone="danger">{{ session('error') }}</x-alert>
        @endif
        @if (session('info'))
            <x-alert tone="info">{{ session('info') }}</x-alert>
        @endif
        @if ($errors->any() && ! session('error'))
            <x-alert tone="danger" title="Please check the form">{{ $errors->first() }}</x-alert>
        @endif
    </div>
@endif
