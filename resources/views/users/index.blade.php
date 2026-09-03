@extends('layouts.app')
@section('title', __('Users'))

@section('content')
    <h1>{{ __('Users') }}</h1>
    <p class="sub">
        @if ($isSystemAdmin)
            {{ __('Every account on the installation.') }}
            · <a href="{{ route('system.users') }}">{{ __('System view') }}</a>
        @else
            {{ __('Accounts in your organization.') }}
        @endif
    </p>

    <form method="GET" action="{{ route('users.index') }}" class="card">
        <div class="row">
            <label>
                <span>{{ __('Search') }}</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Username or email') }}">
            </label>
            <div class="actions">
                <button class="btn" type="submit">{{ __('Search') }}</button>
                <a class="btn ghost" href="{{ route('users.create') }}">{{ __('Add user') }}</a>
            </div>
        </div>
    </form>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Account') }}</th>
                <th>{{ __('System tier') }}</th>
                <th>{{ __('Team role') }}</th>
                <th>{{ __('Organization') }}</th>
                <th>{{ __('Website') }}</th>
                <th>{{ __('Active') }}</th>
                <th></th>
            </tr>
            @foreach ($users as $u)
                <tr>
                    <td>
                        <a href="{{ route('users.edit', $u) }}">{{ $u->username }}</a>
                        @if ($u->id === auth()->id())<span class="chip">{{ __('you') }}</span>@endif
                        <div class="dim small">{{ $u->email }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $u->isSystemAdmin() ? 'critical' : ($u->role === 'owner' ? 'resolved' : 'reported') }}">
                            {{ $u->roleLabel() }}
                        </span>
                    </td>
                    <td class="small">{{ \App\Support\Access::roleLabel($u->orgRole()) }}</td>
                    <td class="small dim">{{ $u->organization?->name ?? '—' }}</td>
                    <td class="small dim">{{ $u->website?->name ?? '—' }}</td>
                    <td>{{ $u->active ? __('Yes') : __('No') }}</td>
                    <td class="right-align">
                        @if ($u->id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline-form"
                                  data-confirm="{{ __('Delete :name?', ['name' => $u->username]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0">{{ __('How the tiers work') }}</h2>
        <table>
            @foreach (\App\Models\User::ROLE_LABELS as $key => $label)
                <tr>
                    <td style="width:180px"><strong>{{ $label }}</strong></td>
                    <td class="dim small">{{ \App\Models\User::ROLE_HINTS[$key] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
