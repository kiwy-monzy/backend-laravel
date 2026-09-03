@extends('layouts.app')
@section('title', __('Numbering'))

@section('content')
    <h1>{{ __('Numbering') }}</h1>
    <p class="sub">{{ $organization?->name }} · {{ __('how references are shaped') }}</p>

    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <p class="dim small" style="margin-top:0">
            {{ __('References are allocated automatically when a record is saved — nobody types them. What you choose here is the prefix and how many digits follow it. Changing a prefix affects records raised from now on; those already issued keep the reference they were given.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('numbering.update') }}">
        @csrf
        @method('PUT')

        <div class="card table-wrap">
            <table>
                <tr>
                    <th>{{ __('Records') }}</th>
                    <th>{{ __('Prefix') }}</th>
                    <th>{{ __('Digits') }}</th>
                    <th>{{ __('Next reference') }}</th>
                </tr>
                @foreach ($sequences as $key => $s)
                    <tr>
                        <td>{{ __($s['label']) }}</td>
                        <td><input type="text" name="prefix[{{ $key }}]" value="{{ $s['prefix'] }}" maxlength="12" style="max-width:120px"></td>
                        <td><input type="number" name="padding[{{ $key }}]" value="{{ $s['padding'] }}" min="1" max="10" style="max-width:80px"></td>
                        <td><code>{{ $s['example'] }}</code></td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="actions">
            <button class="btn" type="submit">{{ __('Save numbering') }}</button>
        </div>
    </form>
@endsection
