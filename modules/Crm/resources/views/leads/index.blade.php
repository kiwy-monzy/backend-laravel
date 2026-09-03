@extends('layouts.app')
@section('title', __('Leads'))

@section('content')
    <h1>{{ __('Leads') }}</h1>
    <p class="sub">
        {{ $organization?->name }} ·
        {{ __('Enquiries from the website and everywhere else, before they become customers.') }}
    </p>

    <div class="grid c3">
        <a class="stat" href="{{ route('crm.leads.index', ['status' => 'open']) }}">
            <div class="n">{{ number_format($counts['open']) }}</div>
            <div class="k">{{ __('Open') }}</div>
        </a>
        <a class="stat" href="{{ route('crm.leads.index', ['status' => 'won']) }}">
            <div class="n">{{ number_format($counts['won']) }}</div>
            <div class="k">{{ __('Won') }}</div>
        </a>
        <div class="stat @if ($counts['overdue']) bad @endif">
            <div class="n">{{ number_format($counts['overdue']) }}</div>
            <div class="k">{{ __('Follow-up overdue') }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('crm.leads.index') }}" class="card">
        <div class="row">
            <label>
                <span>{{ __('Search') }}</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Name, email, phone or message') }}">
            </label>
            <label>
                <span>{{ __('Status') }}</span>
                <select name="status">
                    <option value="">{{ __('All') }}</option>
                    <option value="open" @selected($status === 'open')>{{ __('Open only') }}</option>
                    @foreach (\Modules\Crm\Models\Lead::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Source') }}</span>
                <select name="source">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\Modules\Crm\Models\Lead::SOURCES as $key => $label)
                        <option value="{{ $key }}" @selected($source === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="actions">
                <button class="btn" type="submit">{{ __('Search') }}</button>
                @if ($mayAdd)
                    <a class="btn ghost" href="{{ route('crm.leads.create') }}">{{ __('Add lead') }}</a>
                @endif
            </div>
        </div>
    </form>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Contact') }}</th>
                <th>{{ __('Source') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Owner') }}</th>
                <th>{{ __('Follow up') }}</th>
                <th></th>
            </tr>
            @forelse ($leads as $lead)
                <tr>
                    <td>
                        <a href="{{ route('crm.leads.edit', $lead) }}">{{ $lead->name }}</a>
                        @if ($lead->company)<div class="dim small">{{ $lead->company }}</div>@endif
                        @if ($lead->subject)<div class="dim small">{{ \Illuminate\Support\Str::limit($lead->subject, 40) }}</div>@endif
                    </td>
                    <td class="small">
                        {{ $lead->email }}
                        @if ($lead->phone)<div class="dim">{{ $lead->phone }}</div>@endif
                    </td>
                    <td class="small">
                        <span class="badge">{{ $lead->sourceLabel() }}</span>
                        @if ($lead->website)<div class="dim small">{{ $lead->website->name }}</div>@endif
                    </td>
                    <td>
                        <span class="badge {{ $lead->status === 'won' ? 'resolved' : ($lead->status === 'lost' ? 'offline' : 'reported') }}">
                            {{ $lead->statusLabel() }}
                        </span>
                    </td>
                    <td class="small dim">{{ $lead->owner?->username ?? '—' }}</td>
                    <td class="small @if ($lead->isOverdue()) warn @endif">
                        {{ $lead->follow_up_on?->toDateString() ?? '—' }}
                    </td>
                    <td class="right-align">
                        <div class="actions">
                            @if ($mayEdit && ! $lead->customer_id)
                                <form method="POST" action="{{ route('crm.leads.convert', $lead) }}" class="inline-form">
                                    @csrf
                                    <button class="btn small" type="submit">{{ __('Convert') }}</button>
                                </form>
                            @elseif ($lead->customer)
                                <a class="btn small ghost" href="{{ route('crm.customers.edit', $lead->customer) }}">
                                    {{ __('Customer') }}
                                </a>
                            @endif
                            @if ($mayDelete)
                                <form method="POST" action="{{ route('crm.leads.destroy', $lead) }}" class="inline-form"
                                      data-confirm="{{ __('Delete this lead?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="dim">{{ __('No leads match.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $leads->links() }}
@endsection
