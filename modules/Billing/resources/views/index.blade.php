@extends('layouts.app')
@section('title', __('Billing'))

@section('content')
    <h1>{{ __('Billing') }}</h1>
    <p class="sub">{{ $organization?->name }} — Recurring subscriptions sold to your own customers.</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('billing.records.index') }}">
            <div class="n">{{ number_format($count) }}</div>
            <div class="k">{{ __('Subscriptions') }}</div>
        </a>
        <div class="stat"><div class="n">{{ $total }}</div><div class="k">{{ __('Total value') }}</div></div>
    </div>

    <div class="card table-wrap" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Recent') }}</h2>
        <table>
            <tr>
                @foreach ($columns as $attribute => $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
            @forelse ($recent as $record)
                <tr>
                    @foreach ($columns as $attribute => $label)
                        <td>
                            @if ($loop->first)
                                <a href="{{ route('billing.records.edit', $record) }}">{{ \App\Support\Present::cell($record, $attribute, []) }}</a>
                            @else
                                {!! \App\Support\Present::cell($record, $attribute, [], true) !!}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="dim">{{ __('Nothing here yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="small"><a href="{{ route('billing.records.index') }}">{{ __('All Subscriptions') }}</a></p>
    </div>
@endsection
