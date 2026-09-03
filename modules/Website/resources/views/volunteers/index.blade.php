@extends('layouts.app')
@section('title', __('Volunteers'))

@section('content')
    <h1>{{ __('Volunteers') }}</h1>
    <p class="sub">{{ __('People who offered their time through the public site.') }}</p>

    <p class="nav" style="margin-bottom:14px">
        <a href="{{ route('website.volunteers.index') }}" @class(['on' => ! $status])>{{ __('All') }}</a>
        @foreach ($statuses as $s)
            <a href="{{ route('website.volunteers.index', ['status' => $s]) }}" @class(['on' => $status === $s])>{{ ucfirst($s) }}</a>
        @endforeach
    </p>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Contact') }}</th>
                <th>{{ __('Skills') }}</th>
                <th>{{ __('Availability') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
            @forelse ($volunteers as $v)
                <tr>
                    <td>
                        {{ $v->name }}
                        <div class="dim small">{{ $v->created_at?->format('Y-m-d') }}</div>
                    </td>
                    <td class="small">
                        {{ $v->email }}
                        @if ($v->phone)<div class="dim">{{ $v->phone }}</div>@endif
                    </td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($v->skills, 60) }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($v->availability, 40) }}</td>
                    <td>
                        <form method="POST" action="{{ route('website.volunteers.update', $v) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <select name="status" onchange="this.form.submit()">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}" @selected($v->status === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="right-align">
                        <form method="POST" action="{{ route('website.volunteers.destroy', $v) }}" class="inline-form"
                              data-confirm="{{ __('Delete this volunteer record?') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('No volunteers yet.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $volunteers->links() }}
@endsection
