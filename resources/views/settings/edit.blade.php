@extends('layouts.app')
@section('title', __('Settings'))

@section('content')
    <h1>{{ __('Settings') }}</h1>
    <p class="sub">{{ $user->username }} · {{ $user->roleLabel() }} · {{ $site?->name ?? '—' }}</p>

    <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <span class="avatar avatar-lg" style="flex:0 0 auto">
                @if ($user->profile_image)
                    <img src="{{ $user->profile_image }}" alt="">
                @else
                    <span>{{ $user->initial() }}</span>
                @endif
            </span>
            <div style="flex:1;min-width:260px">
                <x-image-field name="photo_url" :value="$user->profile_image" placeholder="/storage/uploads/… or pick" />
            </div>
            <button class="btn" type="submit">{{ __('Save') }}</button>
        </div>
    </form>

    <x-image-picker />

    <form method="POST" action="{{ route('settings.update') }}" class="card">
        @csrf

        <label>
            <span>{{ __('Username') }}</span>
            <input type="text" name="username" value="{{ old('username', $user->username) }}" maxlength="60">
        </label>

        <label>
            <span>{{ __('Email') }}</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}">
        </label>

        <label>
            <span>{{ __('Text size') }}</span>
            <select name="font">
                @foreach (['small' => __('Small'), 'normal' => __('Normal'), 'large' => __('Large')] as $key => $label)
                    <option value="{{ $key }}" @selected(session('chrome.font', 'normal') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <h2>{{ __('Change password') }}</h2>
        <div class="row">
            <label>
                <span>{{ __('Current password') }}</span>
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label>
                <span>{{ __('New password') }}</span>
                <input type="password" name="new_password" autocomplete="new-password" minlength="8">
            </label>
            <label>
                <span>{{ __('Repeat new password') }}</span>
                <input type="password" name="new_password_confirmation" autocomplete="new-password">
            </label>
        </div>

        <button class="btn" type="submit">{{ __('Save settings') }}</button>
    </form>
@endsection
