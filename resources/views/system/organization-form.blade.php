@extends('layouts.app')
@section('title', __('New organization'))

@section('content')
    <h1>{{ __('New organization') }}</h1>
    <p class="sub"><a href="{{ route('system.index') }}">{{ __('System') }}</a></p>

    <form method="POST" action="{{ route('system.organization.store') }}" class="card">
        @csrf

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="190">
            </label>
            <label>
                <span>{{ __('Slug') }}</span>
                <input type="text" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9-]+" maxlength="60">
            </label>
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Owner') }}</span>
                <select name="owner_id">
                    <option value="">{{ __('— assign later —') }}</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->username }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Plan') }}</span>
                <select name="plan" required>
                    @foreach ($plans as $key => $plan)
                        <option value="{{ $key }}" @selected($key === 'free_trial')>{{ $plan['label'] }} — {{ $plan['tagline'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Currency') }}</span>
                <input type="text" name="currency" value="{{ old('currency', 'TZS') }}" maxlength="8">
            </label>
            <label>
                <span>{{ __('Country') }}</span>
                <input type="text" name="country" value="{{ old('country', 'TZ') }}" maxlength="8">
            </label>
        </div>

        <p class="dim small">
            {{ __('It starts with a 14-day trial and every module its plan covers already granted, so it is usable immediately.') }}
        </p>

        <button class="btn" type="submit">{{ __('Create organization') }}</button>
    </form>
@endsection
