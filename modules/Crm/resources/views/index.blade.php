@extends('layouts.app')
@section('title', __('CRM'))

@section('content')
    <h1>{{ __('CRM') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('crm.customers.index', ['type' => 'customer']) }}">
            <div class="n">{{ number_format($counts['customers']) }}</div>
            <div class="k">{{ __('Customers') }}</div>
        </a>
        <a class="stat" href="{{ route('crm.customers.index', ['type' => 'vendor']) }}">
            <div class="n">{{ number_format($counts['vendors']) }}</div>
            <div class="k">{{ __('Vendors') }}</div>
        </a>
        <div class="stat">
            <div class="n">{{ number_format($counts['inactive']) }}</div>
            <div class="k">{{ __('Inactive') }}</div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Recently added') }}</h2>
        <table>
            @forelse ($recent as $c)
                <tr>
                    <td><a href="{{ route('crm.customers.edit', $c) }}">{{ $c->display_name }}</a></td>
                    <td class="dim small">{{ $c->company_name }}</td>
                    <td class="dim small">{{ $c->email }}</td>
                    <td class="dim small">{{ $c->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td class="dim">{{ __('No customers yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="small"><a href="{{ route('crm.customers.index') }}">{{ __('All customers') }}</a></p>
    </div>
@endsection
