@extends('layouts.app')
@section('title', \Illuminate\Support\Str::plural($title))

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px">
        <div>
            <h1 style="margin:0">{{ \Illuminate\Support\Str::plural($title) }}</h1>
            <p class="sub" style="margin:2px 0 0">{{ $organization?->name }}</p>
        </div>
        @if ($mayAdd)
            <a class="btn" href="{{ route($routeBase . '.create') }}">
                {{ __('Add :title', ['title' => \Illuminate\Support\Str::lower($title)]) }}
            </a>
        @endif
    </div>

    @if ($gridSource)
        <div class="card" style="padding:10px">
            <div data-grid
                 data-src="{{ $gridSource }}"
                 data-columns='@json($gridColumns)'
                 data-filters='@json($gridFilters)'
                 data-row-href="{{ route($routeBase . '.edit', ['record' => '__ID__']) }}"
                 data-per-page="100"
                 data-empty="{{ __('Nothing here yet.') }}"></div>
        </div>
    @else
        {{-- Fallback for a module with no JSON endpoint yet. --}}
        <form method="GET" action="{{ route($routeBase . '.index') }}" class="card">
            <div class="row">
                <label>
                    <span>{{ __('Search') }}</span>
                    <input type="search" name="q" value="{{ $q }}">
                </label>
                <div class="actions"><button class="btn" type="submit">{{ __('Search') }}</button></div>
            </div>
        </form>

        <div class="card table-wrap">
            <table>
                <tr>
                    @foreach ($columns as $attribute => $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th></th>
                </tr>

                @forelse ($records as $record)
                    <tr>
                        @foreach ($columns as $attribute => $label)
                            <td>
                                @if ($loop->first)
                                    <a href="{{ route($routeBase . '.edit', $record) }}">
                                        {{ \App\Support\Present::cell($record, $attribute, $fields) }}
                                    </a>
                                @else
                                    {!! \App\Support\Present::cell($record, $attribute, $fields, true) !!}
                                @endif
                            </td>
                        @endforeach
                        <td class="right-align">
                            @if ($mayDelete)
                                <form method="POST" action="{{ route($routeBase . '.destroy', $record) }}" class="inline-form"
                                      data-confirm="{{ __('Delete this record?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="dim">{{ __('Nothing here yet.') }}</td>
                    </tr>
                @endforelse
            </table>
        </div>

        {{ $records->links() }}
    @endif
@endsection
