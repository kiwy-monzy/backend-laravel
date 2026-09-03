@extends('layouts.app')
@section('title', __('Customer ledger'))

@php
    use Modules\Invoicing\Models\Money;
    $cur = $organization?->currency ?? 'TZS';
@endphp

@section('content')
    <h1>{{ __('Customer ledger') }}</h1>
    <p class="sub">{{ $organization?->name }} · {{ __('billed against paid, per customer') }}</p>

    <div class="grid c4" style="margin-bottom:12px">
        <div class="stat @if ($outstanding > 0) warn @endif">
            <div class="n">{{ Money::format($outstanding, $cur) }}</div>
            <div class="k">{{ __('Outstanding') }}</div>
        </div>
        <div class="stat"><div class="n">{{ number_format($customerCount) }}</div><div class="k">{{ __('Customers billed') }}</div></div>
    </div>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Customer') }}</th>
                <th class="right-align">{{ __('Documents') }}</th>
                <th class="right-align">{{ __('Billed') }}</th>
                <th class="right-align">{{ __('Paid') }}</th>
                <th class="right-align">{{ __('Outstanding') }}</th>
            </tr>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->customer_name ?: __('(no customer)') }}</td>
                    <td class="right-align dim">{{ number_format($r->documents) }}</td>
                    <td class="right-align">{{ Money::format((int) $r->billed, $cur) }}</td>
                    <td class="right-align">{{ Money::format((int) $r->paid, $cur) }}</td>
                    <td class="right-align"><strong>{{ Money::format((int) $r->outstanding, $cur) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="dim">{{ __('No customer invoices yet. ') }}<a href="{{ route('invoicing.invoices.create') }}">{{ __('Create your first invoice') }}</a></td></tr>
            @endforelse
        </table>
    </div>

    {{ $rows->links() }}
@endsection
