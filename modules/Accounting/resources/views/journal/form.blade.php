@extends('layouts.app')
@section('title', $entry->exists ? __('Edit entry') : __('New entry'))

@php
    use Modules\Invoicing\Models\Money;
    $rows = $entry->exists ? $entry->lines : collect();
    // Always offer a few blank rows; the controller drops the ones left empty.
    $blank = max(2, 6 - $rows->count());
@endphp

@section('content')
    <h1>{{ $entry->exists ? $entry->number : __('New journal entry') }}</h1>
    <p class="sub"><a href="{{ route('accounting.journal.index') }}">{{ __('Journal') }}</a></p>

    @if ($errors->any())
        <div class="flash bad"><ul style="margin:0 0 0 18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $entry->exists ? route('accounting.journal.update', $entry->id) : route('accounting.journal.store') }}">
        @csrf
        @if ($entry->exists) @method('PUT') @endif

        <div class="card">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
                <label>
                    <span>{{ __('Date') }}</span>
                    <input type="date" name="entry_date" required
                           value="{{ old('entry_date', optional($entry->entry_date)->toDateString() ?? now()->toDateString()) }}">
                </label>
                <label>
                    <span>{{ __('Reference') }}</span>
                    <input type="text" name="reference" value="{{ old('reference', $entry->reference) }}">
                </label>
                <label style="grid-column:span 2">
                    <span>{{ __('Memo') }}</span>
                    <input type="text" name="memo" value="{{ old('memo', $entry->memo) }}">
                </label>
            </div>
        </div>

        <div class="card table-wrap">
            <p class="dim small" style="margin-top:0">{{ __('Debits must equal credits. Blank rows are ignored.') }}</p>
            <table>
                <tr>
                    <th style="width:40%">{{ __('Account') }}</th>
                    <th>{{ __('Memo') }}</th>
                    <th class="right-align" style="width:16%">{{ __('Debit') }}</th>
                    <th class="right-align" style="width:16%">{{ __('Credit') }}</th>
                </tr>

                @foreach ($rows as $i => $line)
                    <tr>
                        <td>
                            <select name="lines[{{ $i }}][account_id]">
                                <option value="">{{ __('— none —') }}</option>
                                @foreach ($accounts as $a)
                                    <option value="{{ $a->id }}" @selected($line->account_id === $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="lines[{{ $i }}][memo]" value="{{ $line->memo }}"></td>
                        <td><input type="number" step="any" class="right-align" name="lines[{{ $i }}][debit]" value="{{ $line->debit_minor ? Money::toDecimal($line->debit_minor) : '' }}"></td>
                        <td><input type="number" step="any" class="right-align" name="lines[{{ $i }}][credit]" value="{{ $line->credit_minor ? Money::toDecimal($line->credit_minor) : '' }}"></td>
                    </tr>
                @endforeach

                @for ($j = 0; $j < $blank; $j++)
                    @php $i = $rows->count() + $j; @endphp
                    <tr>
                        <td>
                            <select name="lines[{{ $i }}][account_id]">
                                <option value="">{{ __('— none —') }}</option>
                                @foreach ($accounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="lines[{{ $i }}][memo]"></td>
                        <td><input type="number" step="any" class="right-align" name="lines[{{ $i }}][debit]"></td>
                        <td><input type="number" step="any" class="right-align" name="lines[{{ $i }}][credit]"></td>
                    </tr>
                @endfor
            </table>
        </div>

        <div class="actions">
            <button class="btn" type="submit">{{ __('Post entry') }}</button>
            <a class="btn ghost" href="{{ route('accounting.journal.index') }}">{{ __('Cancel') }}</a>
        </div>
    </form>

    @if ($entry->exists)
        <form method="POST" action="{{ route('accounting.journal.destroy', $entry->id) }}" style="margin-top:14px">
            @csrf @method('DELETE')
            <button class="btn small danger" type="submit">{{ __('Delete entry') }}</button>
        </form>
    @endif
@endsection
