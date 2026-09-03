@extends('layouts.app')
@section('title', __('Customers'))

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px">
        <div>
            <h1 style="margin:0">{{ __('Customers') }}</h1>
            <p class="sub" style="margin:2px 0 0">{{ $organization?->name }}</p>
        </div>
        @if ($mayAdd)
            <a class="btn" href="{{ route('crm.customers.create') }}">{{ __('Add customer') }}</a>
        @endif
    </div>

    <p class="nav" style="margin-bottom:12px">
        <a href="{{ route('crm.customers.index') }}" @class(['on' => ! $type])>{{ __('All') }}</a>
        @foreach (\Modules\Crm\Models\Customer::TYPES as $key => $label)
            <a href="{{ route('crm.customers.index', ['type' => $key]) }}"
               @class(['on' => $type === $key])>{{ \Illuminate\Support\Str::plural($label) }}</a>
        @endforeach
    </p>

    <div class="card" style="padding:10px">
        <div data-grid
             data-src="{{ route('crm.customers.data', ['type' => $type]) }}"
             data-columns='@json($gridColumns)'
             data-row-href="{{ route('crm.customers.edit', ['customer' => '__ID__']) }}"
             data-per-page="100"
             data-empty="{{ __('No contacts yet.') }}"></div>
    </div>
@endsection
