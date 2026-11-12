<x-layouts.app title="One-off bill">
    <x-page-header title="Create a one-off bill" :back="route('manage.invoices.index')" back-label="Bills"
                   description="For admission fees, fines, damage or anything outside the monthly bill. The student is notified." />

    @php
        $oldItems = old('items', [['description' => '', 'quantity' => 1, 'unit_price' => '']]);
    @endphp
    <form method="POST" action="{{ route('manage.invoices.store') }}" class="card max-w-4xl"
          x-data="{ items: @js(array_values($oldItems)), add() { this.items.push({ description: '', quantity: 1, unit_price: '' }) }, remove(i) { if (this.items.length > 1) this.items.splice(i, 1) }, get total() { return this.items.reduce((s, it) => s + (parseInt(it.quantity) || 0) * (parseInt(it.unit_price) || 0), 0) } }">
        @csrf
        <div class="card-body space-y-5">
            <x-select name="user_id" label="Student" required placeholder="Choose a student" :value="$selectedStudent"
                      :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->name.'  ('.($s->profile?->student_id ?? 'no ID').($s->activeAllocation ? ', seat '.$s->activeAllocation->seat->code() : '').')'])->all()" />
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-input name="title" label="Bill title" required placeholder="e.g. Hall admission fee 2026" /></div>
                <x-input name="due_date" type="date" label="Due date" required :value="today()->addDays(14)->toDateString()" />
            </div>

            <div>
                <p class="label">Lines</p>
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <table class="table">
                        <thead><tr><th>Description</th><th class="w-24">Qty</th><th class="w-36">Price (৳)</th><th class="w-32 text-right">Amount</th><th class="w-10"></th></tr></thead>
                        <tbody>
                            <template x-for="(item, i) in items" :key="i">
                                <tr>
                                    <td><input type="text" class="input" x-bind:name="`items[${i}][description]`" x-model="item.description" required placeholder="What is charged"></td>
                                    <td><input type="number" min="1" class="input" x-bind:name="`items[${i}][quantity]`" x-model="item.quantity" required></td>
                                    <td><input type="number" min="1" class="input" x-bind:name="`items[${i}][unit_price]`" x-model="item.unit_price" required></td>
                                    <td class="text-right font-medium tabular-nums" x-text="'৳' + ((parseInt(item.quantity) || 0) * (parseInt(item.unit_price) || 0)).toLocaleString('en-IN')"></td>
                                    <td><button type="button" class="btn btn-ghost btn-sm" x-on:click="remove(i)" x-bind:disabled="items.length === 1" aria-label="Remove line"><x-icon name="x" class="size-4" /></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <button type="button" class="btn btn-soft btn-sm" x-on:click="add()"><x-icon name="plus" class="size-3.5" /> Add line</button>
                    <p class="text-sm text-slate-600">Total <span class="ml-2 text-lg font-semibold text-slate-900" x-text="'৳' + total.toLocaleString('en-IN')"></span></p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('manage.invoices.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create bill</button>
        </div>
    </form>
</x-layouts.app>
