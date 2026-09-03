@extends('layouts.app')
@section('title', __('Assets'))

@section('content')
    <h1>{{ __('Assets') }}</h1>
    <p class="sub">{{ $organization?->name }} — Equipment the organization owns and uses - assigned to people, depreciated, and reconciled against inventory.</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('assets.records.index') }}">
            <div class="n">{{ number_format($count) }}</div>
            <div class="k">{{ __('Assets') }}</div>
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
                                <a href="{{ route('assets.records.edit', $record) }}">{{ \App\Support\Present::cell($record, $attribute, []) }}</a>
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
        <p class="small"><a href="{{ route('assets.records.index') }}">{{ __('All Assets') }}</a></p>
    </div>
@endsection
