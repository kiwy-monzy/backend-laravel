@extends('layouts.app')
@section('title', __('Service Hub'))

@section('content')
    <h1>{{ __('Service Hub') }}</h1>
    <p class="sub">{{ $organization?->name }} — {{ __('Providers, services, requests and bookings.') }}</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('servicehub.providers.index') }}">
            <div class="n">{{ number_format($providers) }}</div>
            <div class="k">{{ __('Providers') }}@if ($pendingProviders) <span class="dim">— {{ trans_choice('{1} :count awaiting review|[2,*] :count awaiting review', $pendingProviders, ['count' => $pendingProviders]) }}</span>@endif</div>
        </a>
        <a class="stat" href="{{ route('servicehub.services.index') }}">
            <div class="n">{{ number_format($services) }}</div>
            <div class="k">{{ __('Services offered') }}</div>
        </a>
        <a class="stat" href="{{ route('servicehub.requests.index') }}">
            <div class="n">{{ number_format($openRequests) }}</div>
            <div class="k">{{ __('Open requests') }}</div>
        </a>
        <a class="stat" href="{{ route('servicehub.bookings.index') }}">
            <div class="n">{{ number_format($bookings) }}</div>
            <div class="k">{{ __('Bookings') }}</div>
        </a>
        <div class="stat"><div class="n">{{ $booked }}</div><div class="k">{{ __('Booked value') }}</div></div>
        <div class="stat"><div class="n">{{ $earned }}</div><div class="k">{{ __('Commission earned') }}</div></div>
    </div>

    <div class="card table-wrap" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Latest requests') }}</h2>
        <table>
            <tr>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Zone') }}</th>
                <th>{{ __('Preferred') }}</th>
                <th>{{ __('Provider') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
            @forelse ($recentRequests as $request)
                <tr>
                    <td><a href="{{ route('servicehub.requests.edit', $request) }}">{{ $request->reference ?: '—' }}</a></td>
                    <td>{{ $request->customer ?: '—' }}</td>
                    <td>{{ $request->zone ?: '—' }}</td>
                    <td>{{ $request->preferred_at?->format('Y-m-d H:i') ?: '—' }}</td>
                    <td>{{ $request->provider?->name ?: '—' }}</td>
                    <td>{{ \Modules\ServiceHub\Models\ServiceRequest::STATUSES[$request->status] ?? $request->status }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('Nothing here yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="small"><a href="{{ route('servicehub.requests.index') }}">{{ __('All requests') }}</a></p>
    </div>

    <div class="card table-wrap" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Upcoming bookings') }}</h2>
        <table>
            <tr>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Provider') }}</th>
                <th>{{ __('Scheduled') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Amount') }}</th>
            </tr>
            @forelse ($upcoming as $booking)
                <tr>
                    <td><a href="{{ route('servicehub.bookings.edit', $booking) }}">{{ $booking->reference ?: '—' }}</a></td>
                    <td>{{ $booking->customer ?: '—' }}</td>
                    <td>{{ $booking->provider?->name ?: '—' }}</td>
                    <td>{{ $booking->scheduled_at?->format('Y-m-d H:i') ?: '—' }}</td>
                    <td>{{ \Modules\ServiceHub\Models\Booking::STATUSES[$booking->status] ?? $booking->status }}</td>
                    <td>{{ \Modules\Invoicing\Models\Money::format((int) $booking->amount_minor, $currency) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('Nothing scheduled.') }}</td></tr>
            @endforelse
        </table>
        <p class="small"><a href="{{ route('servicehub.bookings.index') }}">{{ __('All bookings') }}</a></p>
    </div>
@endsection
