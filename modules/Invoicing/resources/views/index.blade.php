@extends('layouts.app')
@section('title', __('Invoicing'))

@section('content')
    <h1>{{ __('Invoicing') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <div class="grid c3">
        <div class="stat"><div class="n">{{ $money['billed'] }}</div><div class="k">{{ __('Billed') }}</div></div>
        <div class="stat"><div class="n">{{ $money['collected'] }}</div><div class="k">{{ __('Collected') }}</div></div>
        <div class="stat warn"><div class="n">{{ $money['outstanding'] }}</div><div class="k">{{ __('Outstanding') }}</div></div>
    </div>

    <div class="grid c3">
        <a class="stat" href="{{ route('invoicing.invoices.index', ['type' => 'invoice']) }}">
            <div class="n">{{ number_format($counts['invoices']) }}</div><div class="k">{{ __('Invoices') }}</div>
        </a>
        <a class="stat" href="{{ route('invoicing.invoices.index', ['type' => 'estimate']) }}">
            <div class="n">{{ number_format($counts['estimates']) }}</div><div class="k">{{ __('Estimates') }}</div>
        </a>
        <a class="stat" href="{{ route('invoicing.items.index') }}">
            <div class="n">{{ number_format($counts['items']) }}</div><div class="k">{{ __('Items') }}</div>
        </a>
    </div>

    <div class="grid c2" style="margin-top:16px">
        <div class="card">
            <h2 style="margin-top:0">{{ __('Overdue') }}</h2>
            <table>
                @forelse ($overdue as $d)
                    <tr>
                        <td><a href="{{ route('invoicing.invoices.edit', $d) }}">{{ $d->number }}</a></td>
                        <td class="small">{{ $d->customer?->display_name ?? '—' }}</td>
                        <td class="right-align">{{ $d->formattedBalance() }}</td>
                        <td class="dim small">{{ $d->due_date?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td class="dim">{{ __('Nothing overdue.') }}</td></tr>
                @endforelse
            </table>
        </div>

        <div class="card">
            <h2 style="margin-top:0">{{ __('Recent') }}</h2>
            <table>
                @forelse ($recent as $d)
                    <tr>
                        <td><a href="{{ route('invoicing.invoices.edit', $d) }}">{{ $d->number }}</a></td>
                        <td class="small">{{ $d->customer?->display_name ?? '—' }}</td>
                        <td class="right-align">{{ $d->formattedTotal() }}</td>
                        <td><span class="badge {{ $d->status === 'paid' ? 'resolved' : 'moderate' }}">{{ $d->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td class="dim">{{ __('Nothing yet.') }}</td></tr>
                @endforelse
            </table>
        </div>
    </div>
@endsection
