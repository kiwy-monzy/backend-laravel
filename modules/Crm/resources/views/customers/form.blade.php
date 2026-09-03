@extends('layouts.app')
@section('title', $customer->exists ? $customer->display_name : __('Add customer'))

@section('content')
    <h1>{{ $customer->exists ? $customer->display_name : __('Add customer') }}</h1>
    <p class="sub"><a href="{{ route('crm.customers.index') }}">{{ __('Customers') }}</a></p>

    <form method="POST"
          action="{{ $customer->exists ? route('crm.customers.update', $customer) : route('crm.customers.store') }}"
          class="card">
        @csrf
        @if ($customer->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Type') }}</span>
                <select name="contact_type" required>
                    @foreach (\Modules\Crm\Models\Customer::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('contact_type', $customer->contact_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Display name') }}</span>
                <input type="text" name="display_name" value="{{ old('display_name', $customer->display_name) }}" required maxlength="190">
            </label>
            <label>
                <span>{{ __('Company') }}</span>
                <input type="text" name="company_name" value="{{ old('company_name', $customer->company_name) }}" maxlength="190">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Salutation') }}</span>
                <input type="text" name="salutation" value="{{ old('salutation', $customer->salutation) }}" maxlength="20">
            </label>
            <label>
                <span>{{ __('First name') }}</span>
                <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" maxlength="90">
            </label>
            <label>
                <span>{{ __('Last name') }}</span>
                <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" maxlength="90">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" value="{{ old('email', $customer->email) }}">
            </label>
            <label>
                <span>{{ __('Phone') }}</span>
                <input type="tel" name="phone" value="{{ old('phone', $customer->phone) }}">
            </label>
            <label>
                <span>{{ __('Mobile') }}</span>
                <input type="tel" name="mobile" value="{{ old('mobile', $customer->mobile) }}">
            </label>
            <label>
                <span>{{ __('Website') }}</span>
                <input type="text" name="website" value="{{ old('website', $customer->website) }}">
            </label>
        </div>

        <h2>{{ __('Billing') }}</h2>
        <div class="row">
            <label>
                <span>{{ __('Currency') }}</span>
                <input type="text" name="currency" value="{{ old('currency', $customer->currency) }}" required maxlength="8">
            </label>
            <label>
                <span>{{ __('Payment terms') }}</span>
                <select name="payment_terms" required>
                    @foreach (\Modules\Crm\Models\Customer::PAYMENT_TERMS as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_terms', $customer->payment_terms) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Credit limit') }}</span>
                <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
            </label>
            <label>
                <span>{{ __('Tax number') }}</span>
                <input type="text" name="tax_number" value="{{ old('tax_number', $customer->tax_number) }}" maxlength="60">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Street') }}</span>
                <input type="text" name="billing_street" value="{{ old('billing_street', $customer->billing_street) }}">
            </label>
            <label>
                <span>{{ __('City') }}</span>
                <input type="text" name="billing_city" value="{{ old('billing_city', $customer->billing_city) }}">
            </label>
            <label>
                <span>{{ __('Region') }}</span>
                <input type="text" name="billing_state" value="{{ old('billing_state', $customer->billing_state) }}">
            </label>
            <label>
                <span>{{ __('Postcode') }}</span>
                <input type="text" name="billing_postcode" value="{{ old('billing_postcode', $customer->billing_postcode) }}" maxlength="32">
            </label>
            <label>
                <span>{{ __('Country') }}</span>
                <input type="text" name="billing_country" value="{{ old('billing_country', $customer->billing_country) }}">
            </label>
        </div>

        <label>
            <span>{{ __('Notes') }}</span>
            <textarea name="notes" maxlength="4000">{{ old('notes', $customer->notes) }}</textarea>
        </label>

        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $customer->active ?? true))>
            <span style="margin:0">{{ __('Active') }}</span>
        </label>

        <button class="btn" type="submit">{{ $customer->exists ? __('Save customer') : __('Create customer') }}</button>
    </form>
@endsection
