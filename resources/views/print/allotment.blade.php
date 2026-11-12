<x-layouts.print title="Seat allotment letter">
    @php
        $student = $allocation->user;
        $profile = $student->profile;
        $room = $allocation->seat->room;
    @endphp
    <div class="mt-8 flex justify-between text-sm text-slate-600">
        <p>Ref: {{ collect(explode(' ', setting('hall_name')))->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->join('') }}/SA/{{ $allocation->start_date->format('Y') }}/{{ str_pad((string) $allocation->id, 4, '0', STR_PAD_LEFT) }}</p>
        <p>Date: {{ $allocation->created_at->format('j F Y') }}</p>
    </div>

    <h1 class="mt-8 text-center text-xl font-bold uppercase tracking-wide text-slate-900 underline decoration-brand-600 decoration-2 underline-offset-8">Seat allotment letter</h1>

    <div class="mt-8 space-y-4 text-[15px] leading-7 text-slate-800">
        <p>To,<br><span class="font-semibold">{{ $student->name }}</span><br>Student ID {{ $profile?->student_id }}, {{ $profile?->department }}, {{ $profile?->yearLabel() }}</p>
        <p>We are pleased to inform you that you have been allotted a seat in {{ setting('hall_name') }} with the following details:</p>
    </div>

    <table class="mx-auto mt-6 w-full max-w-md text-sm">
        <tbody class="divide-y divide-slate-200 border-y border-slate-300">
            <tr><td class="py-2.5 text-slate-500">Room</td><td class="py-2.5 text-right font-semibold">{{ $room->number }}, {{ $room->floorLabel() }}</td></tr>
            <tr><td class="py-2.5 text-slate-500">Seat</td><td class="py-2.5 text-right font-semibold">{{ $allocation->seat->label }} ({{ $allocation->seat->code() }})</td></tr>
            <tr><td class="py-2.5 text-slate-500">Room type</td><td class="py-2.5 text-right font-semibold">{{ $room->typeLabel() }}</td></tr>
            <tr><td class="py-2.5 text-slate-500">Effective from</td><td class="py-2.5 text-right font-semibold">{{ $allocation->start_date->format('j F Y') }}</td></tr>
            <tr><td class="py-2.5 text-slate-500">Seat rent</td><td class="py-2.5 text-right font-semibold">{{ taka($room->monthly_rent) }} per month</td></tr>
            <tr><td class="py-2.5 text-slate-500">Electricity</td><td class="py-2.5 text-right font-semibold">{{ taka((int) setting('electricity_charge')) }} per month</td></tr>
        </tbody>
    </table>

    <div class="mt-8 space-y-2 text-sm leading-6 text-slate-700">
        <p class="font-semibold text-slate-900">Conditions</p>
        <ol class="list-decimal space-y-1 pl-5">
            <li>The seat is for your personal use only and cannot be transferred or sublet.</li>
            <li>Monthly hall bills (seat rent and meals) must be paid by day {{ setting('bill_due_day') }} of the next month.</li>
            <li>Meals are ON by default. Turn them off in the hall portal before {{ \Carbon\Carbon::createFromFormat('H:i', setting('meal_cutoff_time'))->format('g:i A') }} of the previous day.</li>
            <li>Damage to hall property will be charged to the resident.</li>
            <li>The hall authority may cancel this allotment for breaking hall rules.</li>
        </ol>
    </div>

    <div class="mt-16 flex justify-between gap-8 text-center text-xs text-slate-500">
        <div class="w-48 border-t border-slate-400 pt-2">Signature of the student</div>
        <div class="w-48 border-t border-slate-400 pt-2">{{ $allocation->allocatedBy?->name ?? 'Provost' }}<br>For the Provost, {{ setting('hall_name') }}</div>
    </div>
</x-layouts.print>
