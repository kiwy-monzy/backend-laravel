@extends('layouts.app')
@section('title', __('All users'))

@section('content')
    <h1>{{ __('All users') }}</h1>
    <p class="sub">{{ __('Every account on the installation, whatever organization it belongs to.') }}</p>

    <p class="nav" style="margin-bottom:16px">
        <a href="{{ route('system.index') }}">{{ __('Organizations') }}</a>
        <a href="{{ route('system.users') }}" class="on">{{ __('All users') }}</a>
    </p>

    <form method="GET" action="{{ route('system.users') }}" class="card">
        <div class="row">
            <label>
                <span>{{ __('Search') }}</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Username or email') }}">
            </label>
            <label>
                <span>{{ __('Tier') }}</span>
                <select name="role">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Models\User::ROLE_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div><button class="btn" type="submit">{{ __('Search') }}</button></div>
        </div>
    </form>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Account') }}</th>
                <th>{{ __('System tier') }}</th>
                <th>{{ __('Organization') }}</th>
                <th>{{ __('Team role') }}</th>
                <th>{{ __('Active') }}</th>
                <th></th>
            </tr>
            @foreach ($users as $u)
                <tr>
                    <td>
                        {{ $u->username }}
                        @if ($u->id === auth()->id())<span class="chip">{{ __('you') }}</span>@endif
                        <div class="dim small">{{ $u->email }}</div>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('system.users.update', $u) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="organization_id" value="{{ $u->organization_id }}">
                            <input type="hidden" name="active" value="{{ $u->active ? 1 : 0 }}">
                            <select name="role" onchange="this.form.submit()">
                                @foreach (\App\Models\User::ROLE_LABELS as $key => $label)
                                    <option value="{{ $key }}" @selected($u->role === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="small dim">
                        @if ($u->organization)
                            <a href="{{ route('system.organization', $u->organization) }}">{{ $u->organization->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small">{{ \App\Support\Access::roleLabel($u->orgRole()) }}</td>
                    <td>{{ $u->active ? __('Yes') : __('No') }}</td>
                    <td class="right-align">
                        @if ($u->id !== auth()->id())
                            <form method="POST" action="{{ route('system.users.destroy', $u) }}" class="inline-form"
                                  data-confirm="{{ __('Delete :name from the whole installation?', ['name' => $u->username]) }}">
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

    {{ $users->links() }}

    <form method="POST" action="{{ route('system.users.store') }}" class="card">
        @csrf
        <h2 style="margin-top:0">{{ __('Create an account') }}</h2>
        <p class="dim small">
            {{ __('A system admin runs the installation; an owner runs one organization; a member belongs to one. Only this page can mint the first two.') }}
        </p>

        <div class="row">
            <label>
                <span>{{ __('Username') }}</span>
                <input type="text" name="username" required maxlength="60">
            </label>
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" required>
            </label>
            <label>
                <span>{{ __('Password') }}</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('System tier') }}</span>
                <select name="role" required>
                    @foreach (\App\Models\User::ROLE_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($key === 'member')>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Organization') }}</span>
                <select name="organization_id">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Team role') }}</span>
                <select name="org_role">
                    @foreach (\App\Support\Access::ROLES as $r)
                        <option value="{{ $r }}" @selected($r === 'employee')>{{ \App\Support\Access::roleLabel($r) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button class="btn" type="submit">{{ __('Create account') }}</button>
    </form>
@endsection
