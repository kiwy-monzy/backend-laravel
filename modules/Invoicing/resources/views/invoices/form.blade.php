@php
    use Modules\Invoicing\Models\Document;
    use Modules\Invoicing\Models\Money;
    use Modules\Invoicing\Models\Payment;

    $currency = $organization?->currency ?? 'TZS';
    $lines = old('lines') ?? $document->lines->map(fn ($l) => [
        'item_id' => $l->item_id,
        'name' => $l->name,
        'description' => $l->description,
        'quantity' => $l->quantity,
        'rate' => number_format($l->rate_minor / 100, 2, '.', ''),
        'tax_percent' => $l->tax_percent,
    ])->all();

    // Always render one empty row so a new document has something to type into.
    if (empty($lines)) {
        $lines = [['item_id' => '', 'name' => '', 'description' => '', 'quantity' => 1, 'rate' => '0.00', 'tax_percent' => 0]];
    }

    $type = $document->doc_type ?: 'invoice';
    $dueLabel = Document::dueLabelFor($type);
    $partyLabel = Document::partyLabelFor($type);
@endphp

@extends('layouts.app')
@section('title', $document->exists ? $document->number : __('New document'))

@section('content')
    <h1>{{ $document->exists ? $document->number : __('New :type', ['type' => Document::TYPES[$document->doc_type]]) }}</h1>
    <p class="sub">
        <a href="{{ route('invoicing.invoices.index', ['type' => $document->doc_type]) }}">
            {{ \Illuminate\Support\Str::plural(Document::TYPES[$document->doc_type]) }}
        </a>
        @if ($document->exists)
            · <span class="badge {{ $document->isOverdue() ? 'critical' : ($document->status === 'paid' ? 'resolved' : 'moderate') }}">
                {{ $document->statusLabel() }}
            </span>
        @endif
    </p>

    <form method="POST"
          action="{{ $document->exists ? route('invoicing.invoices.update', $document) : route('invoicing.invoices.store') }}"
          class="card">
        @csrf
        @if ($document->exists) @method('PUT') @endif
        <input type="hidden" name="doc_type" value="{{ $document->doc_type }}">

        <div class="row">
            <label class="picker-wrap">
                <span>{{ $partyLabel }}</span>
                <input type="text" data-picker="customers" data-target="#customer_id"
                       placeholder="{{ __('Search customers…') }}"
                       data-empty="{{ __('No matching customer') }}"
                       value="{{ $document->customer?->display_name }}">
                <input type="hidden" name="customer_id" id="customer_id"
                       value="{{ old('customer_id', $document->customer_id) }}">
            </label>
            <label>
                <span>{{ __('Issue date') }}</span>
                <input type="date" name="issue_date"
                       value="{{ old('issue_date', $document->issue_date?->toDateString()) }}" required>
            </label>
            @if ($dueLabel)
                <label>
                    <span>{{ $dueLabel }}</span>
                    <input type="date" name="due_date" value="{{ old('due_date', $document->due_date?->toDateString()) }}">
                </label>
            @endif
            <label>
                <span>{{ __('Currency') }}</span>
                <input type="text" name="currency" value="{{ old('currency', $document->currency ?: $currency) }}" required maxlength="8">
            </label>
            <label>
                <span>{{ __('Reference') }}</span>
                <input type="text" name="reference" value="{{ old('reference', $document->reference) }}" maxlength="120">
            </label>
        </div>

        <h2>{{ __('Lines') }}</h2>
        <div class="table-wrap">
            <table id="lines">
                <tr>
                    <th style="width:22%">{{ __('Item') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th style="width:10%">{{ __('Qty') }}</th>
                    <th style="width:14%">{{ __('Rate') }}</th>
                    <th style="width:10%">{{ __('Tax %') }}</th>
                    <th style="width:14%" class="right-align">{{ __('Amount') }}</th>
                    <th></th>
                </tr>

                @foreach ($lines as $i => $line)
                    <tr class="line">
                        <td class="picker-wrap">
                            <input type="hidden" name="lines[{{ $i }}][item_id]" value="{{ $line['item_id'] ?? '' }}"
                                   class="l-item" id="line-item-{{ $i }}">
                            <input type="text" name="lines[{{ $i }}][name]" value="{{ $line['name'] ?? '' }}"
                                   data-picker="items" data-target="#line-item-{{ $i }}"
                                   class="l-name" placeholder="{{ __('Item or free text') }}"
                                   data-empty="{{ __('No matching item') }}">
                        </td>
                        <td><input type="text" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}"></td>
                        <td><input type="number" step="0.001" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" class="l-qty"></td>
                        <td><input type="number" step="0.01" name="lines[{{ $i }}][rate]" value="{{ $line['rate'] ?? '0.00' }}" class="l-rate"></td>
                        <td><input type="number" step="0.001" name="lines[{{ $i }}][tax_percent]" value="{{ $line['tax_percent'] ?? 0 }}" class="l-tax"></td>
                        <td class="right-align l-amount">0.00</td>
                        <td><button type="button" class="btn small ghost l-remove" aria-label="{{ __('Remove line') }}">×</button></td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="actions" style="margin:10px 0">
            <button class="btn ghost small" type="button" id="add-line">{{ __('Add line') }}</button>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Discount') }}</span>
                <input type="number" step="0.01" min="0" name="discount"
                       value="{{ old('discount', number_format($document->discount_minor / 100, 2, '.', '')) }}" id="discount">
            </label>
            <div>
                <table>
                    <tr><td>{{ __('Subtotal') }}</td><td class="right-align" id="t-subtotal">—</td></tr>
                    <tr><td>{{ __('Tax') }}</td><td class="right-align" id="t-tax">—</td></tr>
                    <tr><td><strong>{{ __('Total') }}</strong></td><td class="right-align"><strong id="t-total">—</strong></td></tr>
                    @if ($document->exists)
                        <tr><td>{{ __('Paid') }}</td><td class="right-align">{{ Money::format($document->paid_minor, $document->currency) }}</td></tr>
                        <tr><td>{{ __('Balance') }}</td><td class="right-align">{{ $document->formattedBalance() }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Notes') }}</span>
                <textarea name="notes" maxlength="4000">{{ old('notes', $document->notes) }}</textarea>
            </label>
            <label>
                <span>{{ __('Terms') }}</span>
                <textarea name="terms" maxlength="4000">{{ old('terms', $document->terms) }}</textarea>
            </label>
        </div>

        <button class="btn" type="submit">{{ $document->exists ? __('Save') : __('Create') }}</button>
    </form>

    @if ($document->exists)
        <div class="card">
            <h2 style="margin-top:0">{{ __('Actions') }}</h2>
            <div class="actions">
                @if ($document->status === 'draft')
                    <form method="POST" action="{{ route('invoicing.invoices.send', $document) }}" class="inline-form">
                        @csrf
                        <button class="btn" type="submit">{{ __('Mark as sent') }}</button>
                    </form>
                @endif
                @if ($document->status !== 'void')
                    <form method="POST" action="{{ route('invoicing.invoices.void', $document) }}" class="inline-form"
                          data-confirm="{{ __('Void :number?', ['number' => $document->number]) }}">
                        @csrf
                        <button class="btn ghost" type="submit">{{ __('Void') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0">{{ __('Payments') }}</h2>
            <table>
                @forelse ($document->payments as $p)
                    <tr>
                        <td>{{ $p->paid_on?->toDateString() }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td class="dim small">{{ $p->reference }}</td>
                        <td class="right-align">{{ Money::format($p->amount_minor, $document->currency) }}</td>
                    </tr>
                @empty
                    <tr><td class="dim">{{ __('No payments recorded.') }}</td></tr>
                @endforelse
            </table>

            @if ($document->balanceMinor() > 0 && $document->status !== 'void')
                <form method="POST" action="{{ route('invoicing.invoices.payments', $document) }}">
                    @csrf
                    <div class="row">
                        <label>
                            <span>{{ __('Amount') }}</span>
                            <input type="number" step="0.01" min="0.01" name="amount"
                                   max="{{ number_format($document->balanceMinor() / 100, 2, '.', '') }}" required>
                        </label>
                        <label>
                            <span>{{ __('Paid on') }}</span>
                            <input type="date" name="paid_on" value="{{ now()->toDateString() }}" required>
                        </label>
                        <label>
                            <span>{{ __('Method') }}</span>
                            <select name="method" required>
                                @foreach (Payment::METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>{{ __('Reference') }}</span>
                            <input type="text" name="reference" maxlength="120">
                        </label>
                        <div><button class="btn" type="submit">{{ __('Record payment') }}</button></div>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                const table = document.getElementById('lines');
                if (!table) return;

                const currency = @json($currency);
                const money = n => currency + ' ' + n.toLocaleString(undefined, {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                });

                function recalc() {
                    let subtotal = 0, tax = 0;

                    table.querySelectorAll('tr.line').forEach(row => {
                        const qty = parseFloat(row.querySelector('.l-qty')?.value) || 0;
                        const rate = parseFloat(row.querySelector('.l-rate')?.value) || 0;
                        const pct = parseFloat(row.querySelector('.l-tax')?.value) || 0;
                        const amount = qty * rate;

                        row.querySelector('.l-amount').textContent = amount.toFixed(2);
                        subtotal += amount;
                        tax += amount * pct / 100;
                    });

                    const discount = parseFloat(document.getElementById('discount')?.value) || 0;
                    document.getElementById('t-subtotal').textContent = money(subtotal);
                    document.getElementById('t-tax').textContent = money(tax);
                    document.getElementById('t-total').textContent = money(Math.max(0, subtotal + tax - discount));
                }

                table.addEventListener('picked', e => {
                    const row = e.target.closest('tr.line');
                    if (!row || e.detail.rate === undefined) return;
                    row.querySelector('.l-rate').value = Number(e.detail.rate).toFixed(2);
                    recalc();
                });

                function wireItemLookup(row) {
                    row.dispatchEvent(new CustomEvent('picker:refresh', { bubbles: true }));
                }

                table.addEventListener('input', recalc);
                document.getElementById('discount')?.addEventListener('input', recalc);

                table.addEventListener('click', e => {
                    if (!e.target.classList.contains('l-remove')) return;
                    const rows = table.querySelectorAll('tr.line');
                    if (rows.length > 1) e.target.closest('tr').remove();
                    recalc();
                });

                document.getElementById('add-line')?.addEventListener('click', () => {
                    const rows = table.querySelectorAll('tr.line');
                    const clone = rows[rows.length - 1].cloneNode(true);
                    const index = rows.length;

                    clone.querySelectorAll('input').forEach(input => {
                        input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                        if (input.classList.contains('l-qty')) input.value = 1;
                        else if (input.classList.contains('l-rate')) input.value = '0.00';
                        else if (input.classList.contains('l-tax')) input.value = 0;
                        else input.value = '';
                    });

                    table.appendChild(clone);
                    wireItemLookup(clone);
                    recalc();
                });

                table.querySelectorAll('tr.line').forEach(wireItemLookup);
                recalc();
            })();
        </script>
    @endpush
@endsection
