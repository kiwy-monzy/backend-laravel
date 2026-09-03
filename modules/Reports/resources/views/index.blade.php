@extends('layouts.app')
@section('title', __('Reports'))

@section('content')
    <h1>{{ __('Reports') }}</h1>
    <p class="sub">{{ __('Dig into any part of the business.') }}</p>

    <div class="grid c2">
        <a class="card" href="{{ route('reports.financial') }}" style="display:block">
            <strong>{{ __('Financial report') }}</strong>
            <div class="dim small" style="margin-top:4px">{{ __('Revenue, cash collected, expenses and what is left.') }}</div>
            @unless ($available['invoicing'])
                <div class="dim small">{{ __('Needs the Invoicing module.') }}</div>
            @endunless
        </a>

        <a class="card" href="{{ route('reports.sales') }}" style="display:block">
            <strong>{{ __('Sales report') }}</strong>
            <div class="dim small" style="margin-top:4px">{{ __('What was invoiced, by month and by status.') }}</div>
            @unless ($available['invoicing'])
                <div class="dim small">{{ __('Needs the Invoicing module.') }}</div>
            @endunless
        </a>

        <a class="card" href="{{ route('reports.customers') }}" style="display:block">
            <strong>{{ __('Customer report') }}</strong>
            <div class="dim small" style="margin-top:4px">{{ __('Top customers by revenue, and who still owes.') }}</div>
            @unless ($available['invoicing'])
                <div class="dim small">{{ __('Needs the Invoicing module.') }}</div>
            @endunless
        </a>

        <a class="card" href="{{ route('reports.inventory') }}" style="display:block">
            <strong>{{ __('Inventory report') }}</strong>
            <div class="dim small" style="margin-top:4px">{{ __('Stock on hand, its value, and what is below reorder level.') }}</div>
            @unless ($available['inventory'])
                <div class="dim small">{{ __('Needs the Inventory module.') }}</div>
            @endunless
        </a>
    </div>

    @unless ($available['invoicing'] || $available['expenses'] || $available['inventory'] || $available['crm'])
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No source data yet') }}</h2>
            <p>{{ __('Add records in other modules to see reports here.') }}</p>
        </div>
    @endunless

    <p class="dim small" style="margin-top:14px">
        {{ __('Add records in other modules to see reports here.') }}
    </p>
@endsection
