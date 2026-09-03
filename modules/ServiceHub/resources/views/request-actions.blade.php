@if ($record->exists)
    <div class="card" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Booking') }}</h2>

        @if ($record->status === 'booked' && $booking = $record->bookings()->first())
            <p class="dim">
                {{ __('Booked as') }}
                <a href="{{ route('servicehub.bookings.edit', $booking) }}">{{ $booking->reference }}</a>.
            </p>
        @elseif (! $record->provider_id)
            <p class="dim">{{ __('Assign a provider above and save, then this request can be booked.') }}</p>
        @else
            <p class="dim">
                {{ __('Creates a booking for :provider.', [
                    'provider' => $record->provider?->name,
                ]) }}
            </p>
            <form method="POST" action="{{ route('servicehub.requests.convert', $record) }}">
                @csrf
                <button class="btn" type="submit">{{ __('Book this request') }}</button>
            </form>
        @endif
    </div>
@endif
