<x-layouts.app title="Hall settings">
    <x-page-header title="Hall settings" description="These values appear on every page, bill and printed document." />

    <form method="POST" action="{{ route('manage.settings.update') }}" class="max-w-4xl space-y-6">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header"><h2 class="card-title">Hall</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <x-input name="hall_name" label="Hall name" required :value="$values['hall_name']" />
                <x-input name="university_name" label="University" required :value="$values['university_name']" />
                <div class="sm:col-span-2"><x-input name="hall_address" label="Address" required :value="$values['hall_address']" /></div>
                <x-input name="contact_phone" label="Office phone" required :value="$values['contact_phone']" />
                <x-input name="contact_email" type="email" label="Office email" required :value="$values['contact_email']" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Dining and billing</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                <x-input name="lunch_rate" type="number" min="0" label="Lunch rate (৳)" required :value="$values['lunch_rate']" />
                <x-input name="dinner_rate" type="number" min="0" label="Dinner rate (৳)" required :value="$values['dinner_rate']" />
                <x-input name="electricity_charge" type="number" min="0" label="Electricity (৳)" required :value="$values['electricity_charge']" hint="Per resident per month." />
                <x-input name="meal_cutoff_time" type="time" label="Meal cutoff" required :value="$values['meal_cutoff_time']" hint="On the day before." />
                <x-input name="bill_due_day" type="number" min="1" max="28" label="Bill due day" required :value="$values['bill_due_day']" hint="Of the next month." />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Payment accounts</h2><p class="text-xs text-slate-500">Shown to students on the pay screen.</p></div></div>
            <div class="card-body grid gap-5 sm:grid-cols-3">
                <x-input name="bkash_number" label="bKash merchant number" :value="$values['bkash_number']" />
                <x-input name="nagad_number" label="Nagad merchant number" :value="$values['nagad_number']" />
                <x-input name="rocket_number" label="Rocket number" :value="$values['rocket_number']" />
                <div class="sm:col-span-3"><x-input name="bank_account" label="Bank account" :value="$values['bank_account']" /></div>
            </div>
        </div>

        <div class="flex justify-end"><button type="submit" class="btn btn-primary">Save settings</button></div>
    </form>
</x-layouts.app>
