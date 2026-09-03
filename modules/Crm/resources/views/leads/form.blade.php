@php use Modules\Crm\Models\Lead; @endphp

@extends('layouts.app')
@section('title', $lead->exists ? $lead->name : __('Add lead'))

@section('content')
    <h1>{{ $lead->exists ? $lead->name : __('Add lead') }}</h1>
    <p class="sub">
        <a href="{{ route('crm.leads.index') }}">{{ __('Leads') }}</a>
        @if ($lead->website) · {{ __('from :site', ['site' => $lead->website->name]) }} @endif
        @if ($lead->customer)
            · <a href="{{ route('crm.customers.edit', $lead->customer) }}">{{ __('converted to a customer') }}</a>
        @endif
    </p>

    <form method="POST"
          action="{{ $lead->exists ? route('crm.leads.update', $lead) : route('crm.leads.store') }}"
          class="card">
        @csrf
        @if ($lead->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name', $lead->name) }}" required maxlength="120">
            </label>
            <label>
                <span>{{ __('Company') }}</span>
                <input type="text" name="company" value="{{ old('company', $lead->company) }}" maxlength="190">
            </label>
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" value="{{ old('email', $lead->email) }}">
            </label>
            <label>
                <span>{{ __('Phone') }}</span>
                <input type="tel" name="phone" value="{{ old('phone', $lead->phone) }}">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Source') }}</span>
                <select name="source" required>
                    @foreach (Lead::SOURCES as $key => $label)
                        <option value="{{ $key }}" @selected(old('source', $lead->source) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Status') }}</span>
                <select name="status" required>
                    @foreach (Lead::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $lead->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Owner') }}</span>
                <select name="owner_id">
                    <option value="">{{ __('— unassigned —') }}</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id', $lead->owner_id) === $owner->id)>
                            {{ $owner->username }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Estimated value') }} ({{ $organization?->currency }})</span>
                <input type="number" step="0.01" min="0" name="value" value="{{ old('value', $lead->value ?? 0) }}">
            </label>
            <label>
                <span>{{ __('Follow up on') }}</span>
                <input type="date" name="follow_up_on"
                       value="{{ old('follow_up_on', $lead->follow_up_on?->toDateString()) }}">
            </label>
        </div>

        <label>
            <span>{{ __('Subject') }}</span>
            <input type="text" name="subject" value="{{ old('subject', $lead->subject) }}" maxlength="190">
        </label>

        <label>
            <span>{{ __('Enquiry') }}</span>
            <textarea name="message" maxlength="5000">{{ old('message', $lead->message) }}</textarea>
        </label>

        <label>
            <span>{{ __('Internal notes') }}</span>
            <textarea name="notes" maxlength="4000">{{ old('notes', $lead->notes) }}</textarea>
        </label>

        <div class="actions">
            <button class="btn" type="submit">{{ $lead->exists ? __('Save lead') : __('Create lead') }}</button>
            @if ($lead->email)
                <a class="btn ghost" href="mailto:{{ $lead->email }}?subject={{ rawurlencode('Re: ' . ($lead->subject ?: 'your enquiry')) }}">
                    {{ __('Reply by email') }}
                </a>
            @endif
        </div>
    </form>

    @if ($lead->exists && ! $lead->customer_id)
        <form method="POST" action="{{ route('crm.leads.convert', $lead) }}" class="card">
            @csrf
            <h2 style="margin-top:0">{{ __('Convert to customer') }}</h2>
            <p class="dim small">
                {{ __('Creates a customer and marks this lead won.') }}
            </p>
            <button class="btn" type="submit">{{ __('Convert') }}</button>
        </form>
    @endif
@endsection
