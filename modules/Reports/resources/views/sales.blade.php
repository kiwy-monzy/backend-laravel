@php use Modules\Invoicing\Models\Document; use Modules\Invoicing\Models\Money; @endphp

@extends('layouts.app')
@section('title', __('Sales report'))

@section('content')
    <h1>{{ __('Sales') }}</h1>
    <p class="sub"><a href="{{ route('reports.index') }}">{{ __('Reports') }}</a> · {{ $organization?->name }}</p>

    @include('reports::_range')

    <div class="grid c3">
        <div class="stat">
            <div class="n">{{ Money::format($total, $currency) }}</div>
            <div class="k">{{ __('Invoiced in period') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ number_format($documents->count()) }}</div>
            <div class="k">{{ __('Invoices') }}</div>
        </div>
        <div class="stat">
            <div class="n">
                {{ $documents->count() ? Money::format((int) round($total / $documents->count()), $currency) : '—' }}
            </div>
            <div class="k">{{ __('Average invoice') }}</div>
        </div>
    </div>

    @if ($documents->isEmpty())
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No sales in this period') }}</h2>
            <p>{{ __('Try a different date range.') }}</p>
        </div>
    @else
        <div class="grid c2" style="margin-top:16px">
            <div class="card">
                <h2 style="margin-top:0">{{ __('By month') }}</h2>
                <table>
                    @forelse ($byMonth as $month => $amount)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</td>
                            <td class="right-align">{{ Money::format($amount, $currency) }}</td>
                        </tr>
                    @empty
                        <tr><td class="dim">{{ __('Nothing invoiced in this period.') }}</td></tr>
                    @endforelse
                </table>
            </div>

            <div class="card">
                <h2 style="margin-top:0">{{ __('By status') }}</h2>
                <table>
                    @forelse ($byStatus as $status => $row)
                        <tr>
                            <td><span class="badge">{{ Document::STATUSES[$status] ?? $status }}</span></td>
                            <td class="right-align dim small">{{ $row['count'] }}</td>
                            <td class="right-align">{{ Money::format($row['total'], $currency) }}</td>
                        </tr>
                    @empty
                        <tr><td class="dim">{{ __('Nothing yet.') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="card table-wrap">
            <h2 style="margin-top:0">{{ __('Invoices') }}</h2>
            <table>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Issued') }}</th>
                    <th class="right-align">{{ __('Total') }}</th>
                    <th class="right-align">{{ __('Balance') }}</th>
                </tr>
                @forelse ($documents as $d)
                    <tr>
                        <td><a href="{{ route('invoicing.invoices.edit', $d) }}">{{ $d->number }}</a></td>
                        <td class="small">{{ $d->customer?->display_name ?? '—' }}</td>
                        <td class="small dim">{{ $d->issue_date?->toDateString() }}</td>
                        <td class="right-align">{{ $d->formattedTotal() }}</td>
                        <td class="right-align">{{ $d->formattedBalance() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="dim">{{ __('Nothing invoiced in this period.') }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif
@endsection
