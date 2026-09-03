@extends('layouts.app')
@section('title', __('General ledger'))

@php
    use Modules\Invoicing\Models\Money;
    $cur = $organization?->currency ?? 'TZS';
@endphp

@section('content')
    <h1>{{ __('General ledger') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <form method="GET" class="card" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <label><span>{{ __('From') }}</span><input type="date" name="from" value="{{ $from }}"></label>
        <label><span>{{ __('To') }}</span><input type="date" name="to" value="{{ $to }}"></label>
        <label>
            <span>{{ __('Account') }}</span>
            <select name="account">
                <option value="">{{ __('All accounts') }}</option>
                @foreach ($accounts as $a)
                    <option value="{{ $a->id }}" @selected($accountId === $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn" type="submit">{{ __('Apply') }}</button>
    </form>

    @if ($accountId && $lines->isNotEmpty())
        <div class="card table-wrap">
            <h2 style="margin-top:0">{{ __('Entries') }}</h2>
            <table>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Memo') }}</th>
                    <th class="right-align">{{ __('Debit') }}</th><th class="right-align">{{ __('Credit') }}</th>
                </tr>
                @foreach ($lines as $l)
                    <tr>
                        <td class="small dim">{{ \Illuminate\Support\Carbon::parse($l->entry_date)->toDateString() }}</td>
                        <td class="small">{{ $l->number }}</td>
                        <td class="small">{{ $l->memo ?: $l->entry_memo ?: '—' }}</td>
                        <td class="right-align">{{ $l->debit_minor ? Money::format($l->debit_minor, $cur) : '' }}</td>
                        <td class="right-align">{{ $l->credit_minor ? Money::format($l->credit_minor, $cur) : '' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="card book table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:80px">{{ __('Code') }}</th>
                    <th>{{ __('Account') }}</th>
                    <th style="width:110px">{{ __('Type') }}</th>
                    <th class="num" style="width:150px">{{ __('Opening') }}</th>
                    <th class="num" style="width:150px">{{ __('Debit') }}</th>
                    <th class="num" style="width:150px">{{ __('Credit') }}</th>
                    <th class="num" style="width:160px">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledger as $l)
                    <tr>
                        <td class="code">{{ $l['account']->code }}</td>
                        <td><a href="{{ route('accounting.journal.ledger', ['account' => $l['account']->id, 'from' => $from, 'to' => $to]) }}">{{ $l['account']->name }}</a></td>
                        <td class="muted">{{ \Modules\Accounting\Models\Account::TYPES[$l['account']->account_type] ?? $l['account']->account_type }}</td>
                        <td class="num muted">{{ Money::format($l['opening'], $cur) }}</td>
                        <td class="num">{{ $l['debit'] ? Money::format($l['debit'], $cur) : '' }}</td>
                        <td class="num">{{ $l['credit'] ? Money::format($l['credit'], $cur) : '' }}</td>
                        <td class="num @if ($l['balance'] < 0) neg @endif">
                            <strong>{{ Money::format($l['balance'], $cur) }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('No accounts yet. ') }}<a href="{{ route('accounting.records.create') }}">{{ __('Create your first account') }}</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
