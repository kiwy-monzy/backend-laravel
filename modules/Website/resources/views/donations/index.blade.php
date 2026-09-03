@extends('layouts.app')
@section('title', __('Donations'))

@section('content')
    <h1>{{ __('Donations') }}</h1>
    <p class="sub">{{ __('Approved total: :total', ['total' => number_format($approvedTotal)]) }}</p>

    <p class="nav" style="margin-bottom:14px">
        <a href="{{ route('website.donations.index') }}" @class(['on' => ! $status])>{{ __('All') }}</a>
        @foreach ($statuses as $s)
            <a href="{{ route('website.donations.index', ['status' => $s]) }}" @class(['on' => $status === $s])>{{ ucfirst($s) }}</a>
        @endforeach
    </p>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Donor') }}</th>
                <th>{{ __('Contact') }}</th>
                <th class="right-align">{{ __('Amount') }}</th>
                <th>{{ __('Proof') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
            @forelse ($donations as $d)
                <tr>
                    <td>
                        {{ $d->name }}
                        <div class="dim small">{{ $d->created_at?->format('Y-m-d H:i') }}</div>
                    </td>
                    <td class="small">
                        {{ $d->email }}
                        @if ($d->phone)<div class="dim">{{ $d->phone }}</div>@endif
                    </td>
                    <td class="right-align">{{ number_format((float) $d->amount) }} {{ $d->currency }}</td>
                    <td>
                        @if ($d->transaction_image)
                            <a href="{{ $d->transaction_image }}" target="_blank" rel="noopener">{{ __('Image') }}</a>
                        @endif
                        @if ($d->transaction_message)
                            <div class="dim small">{{ \Illuminate\Support\Str::limit($d->transaction_message, 40) }}</div>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('website.donations.update', $d) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <select name="status" onchange="this.form.submit()">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}" @selected($d->status === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="right-align">
                        <form method="POST" action="{{ route('website.donations.destroy', $d) }}" class="inline-form"
                              data-confirm="{{ __('Delete this donation record?') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('No donations yet.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $donations->links() }}
@endsection
