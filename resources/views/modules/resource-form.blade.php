@extends('layouts.app')
@section('title', $record->exists ? $title : __('Add :title', ['title' => \Illuminate\Support\Str::lower($title)]))

@section('content')
    <h1>{{ $record->exists ? $title : __('Add :title', ['title' => \Illuminate\Support\Str::lower($title)]) }}</h1>
    <p class="sub">
        <a href="{{ route($routeBase . '.index') }}">{{ \Illuminate\Support\Str::plural($title) }}</a>
    </p>

    <form method="POST"
          action="{{ $record->exists ? route($routeBase . '.update', $record) : route($routeBase . '.store') }}"
          class="card">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="row">
            @foreach ($fields as $field)
                @php $value = old($field->name, $record->{$field->name} ?? $field->default); @endphp

                @if ($field->type === 'checkbox')
                    <label style="display:flex;gap:8px;align-items:center">
                        <input type="checkbox" name="{{ $field->name }}" value="1" style="width:auto" @checked($value)>
                        <span style="margin:0">{{ $field->label }}</span>
                    </label>
                @elseif ($field->type === 'textarea')
                    <label style="flex-basis:100%">
                        <span>{{ $field->label }}</span>
                        <textarea name="{{ $field->name }}" @required($field->required)
                                  maxlength="{{ $field->max }}">{{ $value }}</textarea>
                        @if ($field->help)<span class="dim small">{{ $field->help }}</span>@endif
                    </label>
                @elseif ($field->type === 'select')
                    <label>
                        <span>{{ $field->label }}</span>
                        <select name="{{ $field->name }}" @required($field->required)>
                            @foreach ($field->options as $key => $label)
                                <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($field->help)<span class="dim small">{{ $field->help }}</span>@endif
                    </label>
                @else
                    <label>
                        <span>{{ $field->label }}</span>
                        <input type="{{ $field->type }}"
                               name="{{ $field->name }}"
                               value="{{ $field->type === 'date' && $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : $value }}"
                               @required($field->required)
                               @if ($field->max) maxlength="{{ $field->max }}" @endif
                               @if ($field->min !== null) min="{{ $field->min }}" @endif
                               @if ($field->step !== null) step="{{ $field->step }}" @endif>
                        @if ($field->help)<span class="dim small">{{ $field->help }}</span>@endif
                    </label>
                @endif
            @endforeach
        </div>

        <button class="btn" type="submit">
            {{ $record->exists ? __('Save') : __('Create') }}
        </button>
    </form>

    {{-- A module may add one more action under the form; see ResourceModuleController::formExtras(). --}}
    @isset($formActions)
        @include($formActions)
    @endisset
@endsection
