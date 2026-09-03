@php
    use Modules\Invoicing\Models\Money;

    // Trailing zeros on a quantity read as false precision: "12" not "12.000".
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
@endphp

@extends('layouts.app')
@section('title', __('Inventory report'))

@section('content')
    <h1>{{ __('Inventory') }}</h1>
    <p class="sub"><a href="{{ route('reports.index') }}">{{ __('Reports') }}</a> · {{ $organization?->name }}</p>

    <div class="grid c3">
        <div class="stat">
            <div class="n">{{ number_format($lines) }}</div>
            <div class="k">{{ __('Stock lines') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ Money::format($value, $currency) }}</div>
            <div class="k">{{ __('Value at cost') }}</div>
        </div>
        <div class="stat @if ($low->count()) bad @endif">
            <div class="n">{{ number_format($low->count()) }}</div>
            <div class="k">{{ __('Below reorder level') }}</div>
        </div>
    </div>

    @if ($lines === 0)
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No inventory data') }}</h2>
            <p>{{ __('Add stock items to get started.') }}</p>
        </div>
    @else
        @if ($low->isNotEmpty())
            <div class="card">
                <h2 style="margin-top:0">{{ __('Reorder these') }}</h2>
                <table>
                    @foreach ($low as $item)
                        <tr>
                            <td>{{ $item->item_name }}</td>
                            <td class="dim small">{{ $item->location }}</td>
                            <td class="right-align">{{ $qty($item->quantity) }}</td>
                            <td class="right-align dim small">
                                {{ __('reorder at :n', ['n' => $qty($item->reorder_level)]) }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="card table-wrap">
            <h2 style="margin-top:0">{{ __('Lines needing attention') }}</h2>
            <table>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('SKU') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th class="right-align">{{ __('On hand') }}</th>
                    <th class="right-align">{{ __('Value') }}</th>
                </tr>
                @forelse ($stock as $item)
                    <tr>
                        <td><a href="{{ route('inventory.records.edit', $item) }}">{{ $item->item_name }}</a></td>
                        <td class="dim small">{{ $item->sku ?: '—' }}</td>
                        <td class="dim small">{{ $item->location }}</td>
                        <td class="right-align">{{ $qty($item->quantity) }}</td>
                        <td class="right-align">
                            {{ Money::format((int) ($item->quantity * $item->unit_cost_minor), $currency) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="dim">{{ __('No stock recorded.') }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif
@endsection
