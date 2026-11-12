{{ match (true) { now()->hour < 12 => 'Good morning', now()->hour < 17 => 'Good afternoon', default => 'Good evening' } }}, {{ auth()->user()->firstName() }}
