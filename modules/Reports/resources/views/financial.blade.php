@php use Modules\Invoicing\Models\Money; @endphp

@extends('layouts.app')
@section('title', __('Financial report'))

@section('content')
    <h1>{{ __('Profit and loss') }}</h1>
    <p class="sub">
        <a href="{{ route('reports.index') }}">{{ __('Reports') }}</a> · {{ $organization?->name }}
    </p>

    @include('reports::_range')

    <div class="grid c3">
        @foreach ($rows as [$label, $amount, $emphasis])
            <div class="stat @if ($emphasis && $amount < 0) bad @endif">
                <div class="n">{{ Money::format($amount, $currency) }}</div>
                <div class="k">{{ __($label) }}</div>
            </div>
        @endforeach
    </div>

    @if ($invoiceCount === 0 && !$has['invoicing'])
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No invoicing data') }}</h2>
            <p>{{ __('Add invoices to get started.') }}</p>
        </div>
    @else
        <div class="card">
            <h2 style="margin-top:0">{{ __('How this is worked out') }}</h2>
            <table>
                @foreach ($rows as [$label, $amount, $emphasis])
                    <tr>
                        <td>{{ __($label) }}</td>
                        <td class="right-align">{{ Money::format($amount, $currency) }}</td>
                    </tr>
                @endforeach
            </table>

            <p class="dim small" style="margin-top:10px">
                {{ __('Revenue counts invoiced amounts. Expenses exclude rejected claims.') }}
            </p>

            @if ($has['inventory'])
                <p class="dim small">
                    {{ __('Stock on hand is :value at cost, shown separately.', ['value' => Money::format($stockValue, $currency)]) }}
                </p>
            @endif

            @unless ($has['expenses'])
                <p class="dim small">{{ __('The Expenses module is not enabled, so this shows revenue only.') }}</p>
            @endunless
        </div>
    @endif
@endsection
