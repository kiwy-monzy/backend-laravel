@extends('layouts.app')
@section('title', __('Accounting'))

@section('content')
    <h1>{{ __('Accounting') }}</h1>
    <p class="sub">{{ $organization?->name }} — Chart of accounts and journal entries behind the ledger.</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('accounting.records.index') }}">
            <div class="n">{{ number_format($count) }}</div>
            <div class="k">{{ __('Accounts') }}</div>
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
                                <a href="{{ route('accounting.records.edit', $record) }}">{{ \App\Support\Present::cell($record, $attribute, []) }}</a>
                            @else
                                {!! \App\Support\Present::cell($record, $attribute, [], true) !!}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="dim">{{ __('No accounts yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="small"><a href="{{ route('accounting.records.index') }}">{{ __('All Accounts') }}</a></p>
    </div>

    @if ($count === 0)
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('Get started with Accounting') }}</h2>
            <p>{{ __('Create accounts to get started.') }}</p>
            <a class="btn" href="{{ route('accounting.records.create') }}">{{ __('Create your first account') }}</a>
        </div>
    @endif
@endsection
