@extends('layouts.app')
@section('title', __('Trial balance'))

@php
    use Modules\Invoicing\Models\Money;
    $cur = $organization?->currency ?? 'TZS';
@endphp

@section('content')
    <h1>{{ __('Trial balance') }}</h1>
    <p class="sub">{{ $organization?->name }} · {{ $from }} → {{ $to }}</p>

    <form method="GET" class="card" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <label><span>{{ __('From') }}</span><input type="date" name="from" value="{{ $from }}"></label>
        <label><span>{{ __('To') }}</span><input type="date" name="to" value="{{ $to }}"></label>
        <button class="btn" type="submit">{{ __('Apply') }}</button>
        <span class="spacer"></span>
        <span class="{{ $trial['balanced'] ? 'agrees' : 'disagrees' }}">
            {{ $trial['balanced'] ? __('In balance') : __('Out of balance') }}
        </span>
    </form>

    @if (empty($trial['rows']))
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No posting data') }}</h2>
            <p>{{ __('Post journal entries first.') }}</p>
        </div>
    @else
        <div class="card book table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:90px">{{ __('Code') }}</th>
                        <th>{{ __('Account') }}</th>
                        <th class="num" style="width:170px">{{ __('Debit') }}</th>
                        <th class="num" style="width:170px">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trial['rows'] as $r)
                        <tr>
                            <td class="code">{{ $r['account']->code }}</td>
                            <td>{{ $r['account']->name }}</td>
                            <td class="num">{{ $r['debit'] ? Money::format($r['debit'], $cur) : '' }}</td>
                            <td class="num">{{ $r['credit'] ? Money::format($r['credit'], $cur) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('Nothing posted in this period.') }}</td></tr>
                    @endforelse

                    <tr class="total">
                        <td></td>
                        <td>{{ __('Total') }}</td>
                        <td class="num">{{ Money::format($trial['debit_total'], $cur) }}</td>
                        <td class="num">{{ Money::format($trial['credit_total'], $cur) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    @unless ($trial['balanced'])
        <div class="flash bad">
            {{ __('Debits and credits differ by :amount.', [
                'amount' => Money::format(abs($trial['debit_total'] - $trial['credit_total']), $cur),
            ]) }}
        </div>
    @endunless
@endsection
