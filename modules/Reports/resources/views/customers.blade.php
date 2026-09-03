@php use Modules\Invoicing\Models\Money; @endphp

@extends('layouts.app')
@section('title', __('Customer report'))

@section('content')
    <h1>{{ __('Customers') }}</h1>
    <p class="sub"><a href="{{ route('reports.index') }}">{{ __('Reports') }}</a> · {{ $organization?->name }}</p>

    @include('reports::_range')

    <div class="grid c3">
        <div class="stat">
            <div class="n">{{ number_format($customerCount) }}</div>
            <div class="k">{{ __('On the books') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ number_format($rows->count()) }}</div>
            <div class="k">{{ __('Invoiced in period') }}</div>
        </div>
        <div class="stat @if ($rows->sum('owing') > 0) warn @endif">
            <div class="n">{{ Money::format((int) $rows->sum('owing'), $currency) }}</div>
            <div class="k">{{ __('Still owing') }}</div>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No customer activity in this period') }}</h2>
            <p>{{ __('Try a different date range.') }}</p>
        </div>
    @else
        <div class="card table-wrap">
            <table>
                <tr>
                    <th>{{ __('Customer') }}</th>
                    <th class="right-align">{{ __('Invoices') }}</th>
                    <th class="right-align">{{ __('Billed') }}</th>
                    <th class="right-align">{{ __('Paid') }}</th>
                    <th class="right-align">{{ __('Owing') }}</th>
                </tr>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="right-align">{{ $row['invoices'] }}</td>
                        <td class="right-align">{{ Money::format($row['billed'], $currency) }}</td>
                        <td class="right-align">{{ Money::format($row['paid'], $currency) }}</td>
                        <td class="right-align">{{ Money::format($row['owing'], $currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="dim">{{ __('No invoices in this period.') }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif
@endsection
