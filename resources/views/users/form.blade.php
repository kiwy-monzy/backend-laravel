@extends('layouts.app')
@section('title', $user->exists ? $user->username : __('Add user'))

@section('content')
    <h1>{{ $user->exists ? $user->username : __('Add user') }}</h1>
    <p class="sub"><a href="{{ route('users.index') }}">{{ __('Users') }}</a></p>

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Username') }}</span>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required maxlength="60">
            </label>
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </label>
            <label>
                <span>{{ $user->exists ? __('New password (blank to keep)') : __('Password') }}</span>
                <input type="password" name="password" autocomplete="new-password" @required(! $user->exists) minlength="8">
            </label>
        </div>

        <h2>{{ __('System tier') }}</h2>
        <p class="dim small">{{ __('What this account is to the installation.') }}</p>
        <div class="template-picker">
            @foreach ($roles as $role)
                <label>
                    <input type="radio" name="role" value="{{ $role }}"
                           @checked(old('role', $user->role ?? 'member') === $role)>
                    <strong>{{ \App\Models\User::ROLE_LABELS[$role] }}</strong>
                    <div class="desc">{{ \App\Models\User::ROLE_HINTS[$role] }}</div>
                </label>
            @endforeach
        </div>

        @unless (auth()->user()->isSystemAdmin())
            <p class="dim small">
                {{ __('Only a system admin can create another system admin or an organization owner.') }}
            </p>
        @endunless

        <h2>{{ __('Organization') }}</h2>
        <div class="row">
            <label>
                <span>{{ __('Organization') }}</span>
                <select name="organization_id" @disabled(! auth()->user()->isSystemAdmin())>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" @selected(old('organization_id', $user->organization_id) === $org->id)>
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Team role') }}</span>
                <select name="org_role" required>
                    @foreach ($orgRoles as $r)
                        <option value="{{ $r }}" @selected(old('org_role', $member?->role ?? 'employee') === $r)>
                            {{ \App\Support\Access::roleLabel($r) }} — {{ \App\Support\Access::ROLE_HINTS[$r] }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Website') }}</span>
                <select name="website_id">
                    <option value="">{{ __('— none —') }}</option>
                    @foreach ($websites as $w)
                        <option value="{{ $w->id }}" @selected(old('website_id', $user->website_id) === $w->id)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $user->active ?? true))>
            <span style="margin:0">{{ __('Account is active') }}</span>
        </label>

        <button class="btn" type="submit">{{ $user->exists ? __('Save user') : __('Create user') }}</button>
    </form>
@endsection
