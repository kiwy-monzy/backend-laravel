@extends('layouts.app')
@section('title', __('Websites'))

@section('content')
    <h1>{{ __('Websites') }}</h1>
    <p class="sub">
        {{ $organization?->name }} ·
        {{ trans_choice(':count site|:count sites', $sites->count(), ['count' => $sites->count()]) }}
    </p>

    @if ($canAdd)
        <p><a class="btn" href="{{ route('website.sites.create') }}">{{ __('Add website') }}</a></p>
    @endif

    @if ($sites->isEmpty())
        <div class="card">
            <p class="dim">{{ __('This organization has no websites yet.') }}</p>
        </div>
    @else
        <div class="grid c2">
            @foreach ($sites as $s)
                @php $stat = $stats[$s->id]; @endphp
                <div class="card">
                    <div class="body">
                        <h3 style="margin-bottom:2px">
                            <a href="{{ route('website.sites.show', $s) }}">{{ $s->name }}</a>
                            @if ($current?->id === $s->id)
                                <span class="chip">{{ __('selected') }}</span>
                            @endif
                        </h3>
                        <div class="dim small">
                            {{ $s->domain ?: '/s/' . $s->slug }} ·
                            {{ \App\Support\Templates::ALL[$s->templateKey()]['label'] }} ·
                            <span class="badge {{ $s->is_active ? 'resolved' : 'offline' }}">
                                {{ $s->is_active ? __('live') : __('offline') }}
                            </span>
                        </div>

                        <table style="margin-top:10px">
                            <tr>
                                <td>{{ __('Sections filled') }}</td>
                                <td class="right-align">{{ $stat['filled'] }} / {{ count(\App\Models\Website::SECTIONS) }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Gallery images') }}</td>
                                <td class="right-align">{{ $stat['gallery'] }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Donations') }}</td>
                                <td class="right-align">{{ $stat['donations'] }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Volunteers') }}</td>
                                <td class="right-align">{{ $stat['volunteers'] }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Unread messages') }}</td>
                                <td class="right-align">{{ $stat['unread'] }}</td>
                            </tr>
                        </table>

                        <div class="actions" style="margin-top:12px">
                            <a class="btn small" href="{{ route('website.sites.show', $s) }}">{{ __('Open') }}</a>
                            <a class="btn small ghost" href="{{ site_url($s, 'home') }}"
                               target="_blank" rel="noopener">{{ __('View site') }}</a>
                            @if ($canAdd)
                                <a class="btn small ghost" href="{{ route('website.sites.edit', $s) }}">{{ __('Settings') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
