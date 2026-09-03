@extends('layouts.app')
@section('title', __('Mail'))

@section('content')
    <h1>{{ __('Mail') }}</h1>
    <p class="sub">
        {{ __('The IMAP/SMTP account this admin reads and sends mail through.') }}
        @if ($config?->linked_at)
            · {{ __('Linked :when', ['when' => \Illuminate\Support\Carbon::parse($config->linked_at)->diffForHumans()]) }}
        @endif
    </p>

    <form method="POST" action="{{ route('mail.save') }}" class="card">
        @csrf

        <div class="row">
            <label>
                <span>{{ __('Email address') }}</span>
                <input type="email" name="email" value="{{ old('email', $config?->email) }}" required>
            </label>
            <label>
                <span>{{ __('Username') }}</span>
                <input type="text" name="username" value="{{ old('username', $config?->username) }}" required>
            </label>
            <label>
                <span>{{ __('Password') }}</span>
                <input type="password" name="password" autocomplete="new-password"
                       placeholder="{{ $config ? __('unchanged') : '' }}">
            </label>
        </div>

        <h2>{{ __('Incoming') }}</h2>
        <div class="row">
            <label>
                <span>{{ __('Host') }}</span>
                <input type="text" name="incoming_host" value="{{ old('incoming_host', $config?->incoming_host) }}" required>
            </label>
            <label>
                <span>{{ __('Port') }}</span>
                <input type="number" name="incoming_port" value="{{ old('incoming_port', $config?->incoming_port ?? 993) }}" required>
            </label>
            <label>
                <span>{{ __('Protocol') }}</span>
                <select name="incoming_protocol">
                    @foreach (['imap' => 'IMAP', 'pop3' => 'POP3'] as $k => $l)
                        <option value="{{ $k }}" @selected(old('incoming_protocol', $config?->incoming_protocol ?? 'imap') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Security') }}</span>
                <select name="incoming_security">
                    @foreach (['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => __('None')] as $k => $l)
                        <option value="{{ $k }}" @selected(old('incoming_security', $config?->incoming_security ?? 'ssl') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <h2>{{ __('Outgoing') }}</h2>
        <div class="row">
            <label>
                <span>{{ __('Host') }}</span>
                <input type="text" name="outgoing_host" value="{{ old('outgoing_host', $config?->outgoing_host) }}" required>
            </label>
            <label>
                <span>{{ __('Port') }}</span>
                <input type="number" name="outgoing_port" value="{{ old('outgoing_port', $config?->outgoing_port ?? 465) }}" required>
            </label>
            <label>
                <span>{{ __('Security') }}</span>
                <select name="outgoing_security">
                    @foreach (['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => __('None')] as $k => $l)
                        <option value="{{ $k }}" @selected(old('outgoing_security', $config?->outgoing_security ?? 'ssl') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button class="btn" type="submit">{{ __('Save mail account') }}</button>
    </form>
@endsection
