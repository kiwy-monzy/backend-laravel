@extends('layouts.app')
@section('title', __('Settings'))

@section('content')
    <h1>{{ __('Settings') }}</h1>
    <p class="sub">{{ $user->username }} · {{ $user->roleLabel() }} · {{ $site?->name ?? '—' }}</p>

    {{-- The portrait is its own form because it posts a file, and mixing an
         upload into the details form would make saving an email address
         re-upload the picture. --}}
    <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="person-cell">
            <label class="avatar avatar-lg" title="{{ __('Change portrait') }}">
                @if ($user->profile_image)
                    <img src="{{ $user->profile_image }}" alt="">
                @else
                    <span>{{ $user->initial() }}</span>
                @endif
                <input type="file" name="avatar" accept="image/*" onchange="this.form.submit()">
            </label>
            <div>
                <strong>{{ __('Your portrait') }}</strong>
                <div class="dim small">{{ __('Click the circle to upload. Stored in your organization’s files.') }}</div>
            </div>
        </div>

        {{-- Or reuse a picture the organization already holds, rather than
             uploading the same photograph a second time. --}}
        <div style="margin-top:12px;border-top:1px solid var(--line);padding-top:12px">
            <x-image-field name="photo_url" :label="__('Or choose from your organization’s files')"
                           :value="$user->profile_image" />
            <button class="btn small" type="submit">{{ __('Use this picture') }}</button>
        </div>
    </form>

    <x-image-picker />

    <form method="POST" action="{{ route('settings.update') }}" class="card">
        @csrf

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
