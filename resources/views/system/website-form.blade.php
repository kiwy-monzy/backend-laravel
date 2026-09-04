@extends('layouts.app')
@section('title', __('Add website for :org', ['org' => $organization->name]))

@section('content')
    <h1>{{ __('Add website for :org', ['org' => $organization->name]) }}</h1>
    <p class="sub"><a href="{{ route('system.organization', $organization) }}">{{ $organization->name }}</a> · {{ $organization->slug }}</p>

    <form method="POST" action="{{ route('system.organization.website.store', $organization) }}" class="card">
        @csrf

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            </label>
            <label>
                <span>{{ __('Slug') }}</span>
                <input type="text" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9-]+" maxlength="60" placeholder="my-site">
                <span class="dim small">{{ __('Used as /s/{slug} and for the directory.') }}</span>
            </label>
            <label>
                <span>{{ __('Domain (optional)') }}</span>
                <input type="text" name="domain" value="{{ old('domain') }}" maxlength="190" placeholder="example.or.tz">
            </label>
        </div>

        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="is_active" value="1" style="width:auto" @checked(old('is_active', true))>
            <span style="margin:0">{{ __('Site is live') }}</span>
        </label>

        <div class="row">
            <label>
                <span>{{ __('Owner (optional)') }}</span>
                <select name="owner_id">
                    <option value="">{{ __('Use organization owner') }}</option>
                    @foreach ($owners as $u)
                        <option value="{{ $u->id }}" @selected(old('owner_id') === $u->id)>{{ $u->username }} ({{ $u->roleLabel() }})</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div style="display:flex;gap:8px;margin-top:12px">
            <button class="btn" type="submit">{{ __('Create website') }}</button>
            <a class="btn ghost" href="{{ route('system.organization', $organization) }}">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
