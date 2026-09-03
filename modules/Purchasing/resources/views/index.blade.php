@extends('layouts.app')
@section('title', __('Purchasing'))

@section('content')
    <h1>{{ __('Purchasing') }}</h1>
    <p class="sub">{{ $organization?->name }} — Purchase orders to vendors, and the bills that follow them.</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('purchasing.records.index') }}">
            <div class="n">{{ number_format($count) }}</div>
            <div class="k">{{ __('Purchase orders') }}</div>
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
                                <a href="{{ route('purchasing.records.edit', $record) }}">{{ \App\Support\Present::cell($record, $attribute, []) }}</a>
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
        <p class="small"><a href="{{ route('purchasing.records.index') }}">{{ __('All Purchase orders') }}</a></p>
    </div>
@endsection
