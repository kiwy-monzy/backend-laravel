@extends('layouts.app')
@section('title', __('Fixed assets'))

@php
    use Modules\Invoicing\Models\Money;
    $cur = $organization?->currency ?? 'TZS';
@endphp

@section('content')
    <h1>{{ __('Fixed assets') }}</h1>
    <p class="sub">{{ $organization?->name }} · {{ __('asset accounts and what they carry') }}</p>

    <div class="grid c4" style="margin-bottom:12px">
        <div class="stat"><div class="n">{{ Money::format($total, $cur) }}</div><div class="k">{{ __('Carrying value') }}</div></div>
        <div class="stat"><div class="n">{{ $ledger->count() }}</div><div class="k">{{ __('Asset accounts') }}</div></div>
    </div>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Account') }}</th>
                <th class="right-align">{{ __('Opening') }}</th>
                <th class="right-align">{{ __('Movement') }}</th>
                <th class="right-align">{{ __('Carrying value') }}</th>
            </tr>
            @forelse ($ledger as $l)
                <tr>
                    <td class="small">{{ $l['account']->code }}</td>
                    <td><a href="{{ route('accounting.journal.ledger', ['account' => $l['account']->id]) }}">{{ $l['account']->name }}</a></td>
                    <td class="right-align dim">{{ Money::format($l['opening'], $cur) }}</td>
                    <td class="right-align">{{ Money::format($l['debit'] - $l['credit'], $cur) }}</td>
                    <td class="right-align"><strong>{{ Money::format($l['balance'], $cur) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="dim">{{ __('No asset accounts yet. ') }}<a href="{{ route('accounting.records.create') }}">{{ __('Create an asset account') }}</a></td></tr>
            @endforelse
        </table>
    </div>
@endsection
