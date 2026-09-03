@extends('layouts.app')
@section('title', __('Messages'))

@section('content')
    <h1>{{ __('Messages') }}</h1>
    <p class="sub">{{ __('Enquiries sent through the public contact form.') }}</p>

    <p class="nav" style="margin-bottom:14px">
        <a href="{{ route('website.messages.index') }}" @class(['on' => ! $status])>{{ __('All') }}</a>
        @foreach ($statuses as $s)
            <a href="{{ route('website.messages.index', ['status' => $s]) }}" @class(['on' => $status === $s])>{{ ucfirst($s) }}</a>
        @endforeach
    </p>

    @forelse ($messages as $m)
        <div class="card">
            <div class="row" style="align-items:flex-start">
                <div style="flex:3 1 320px">
                    <strong>{{ $m->subject ?: __('(no subject)') }}</strong>
                    <div class="dim small">
                        {{ $m->name }} · {{ $m->email }}
                        @if ($m->phone) · {{ $m->phone }} @endif
                        · {{ $m->created_at?->format('Y-m-d H:i') }}
                    </div>
                    <p style="margin:10px 0 0;white-space:pre-wrap">{{ $m->message }}</p>
                </div>

                <div style="flex:0 0 auto" class="actions">
                    <form method="POST" action="{{ route('website.messages.update', $m) }}" class="inline-form">
                        @csrf
                        @method('PUT')
                        <select name="status" onchange="this.form.submit()">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected($m->status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </form>

                    <a class="btn small ghost" href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: ' . ($m->subject ?: 'your message')) }}">
                        {{ __('Reply') }}
                    </a>

                    <form method="POST" action="{{ route('website.messages.destroy', $m) }}" class="inline-form"
                          data-confirm="{{ __('Delete this message?') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="dim">{{ __('No messages yet.') }}</p>
    @endforelse

    {{ $messages->links() }}
@endsection
