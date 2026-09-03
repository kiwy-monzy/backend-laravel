@extends('layouts.app')
@section('title', __('Dashboard'))

@section('content')
    <h1>{{ __('Dashboard') }}</h1>
    <p class="sub">
        {{ $site?->name ?? __('No website selected') }}
        @if ($site)
            · <a href="{{ site_url($site, 'home') }}" target="_blank" rel="noopener">{{ __('View site') }}</a>
            · <span class="chip">{{ \App\Support\Templates::ALL[$site->templateKey()]['label'] }}</span>
        @endif
    </p>

    {{-- The organization's own work comes first: the website statistics below
         are about one section of the product, and leading with them made a
         business with a hundred thousand records look empty. --}}
    @if (! empty($moduleStats))
        <h2>{{ __('Your modules') }}</h2>
        <div class="module-stats">
            @foreach ($moduleStats as $slug => $module)
                <div class="card module-stat">
                    <div class="module-stat-head">
                        <span class="module-stat-icon">
                            {!! \App\Support\Nav::ICON[$module['icon']] ?? \App\Support\Nav::ICON['module'] !!}
                        </span>
                        <a href="{{ route($slug . '.index') }}"><strong>{{ $module['label'] }}</strong></a>
                    </div>
                    @foreach ($module['rows'] as $row)
                        <div class="module-stat-row">
                            @if ($row['route'])
                                <a href="{{ $row['route'] }}">{{ $row['label'] }}</a>
                            @else
                                <span>{{ $row['label'] }}</span>
                            @endif
                            <strong>{{ number_format($row['count']) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <h2>{{ __('Content') }}</h2>
    <div class="grid c4">
        <div class="stat"><div class="n">{{ number_format($counts['websites']) }}</div><div class="k">{{ __('Websites') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['users']) }}</div><div class="k">{{ __('Users') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['gallery']) }}</div><div class="k">{{ __('Gallery images') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['uploads']) }}</div><div class="k">{{ __('Files') }}</div></div>
    </div>

    <h2>{{ __('Engagement') }}</h2>
    <div class="grid c4">
        <div class="stat"><div class="n">{{ number_format($engagement['donations']) }}</div><div class="k">{{ __('Donations') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($engagement['raised']) }}</div><div class="k">{{ __('Approved total') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($engagement['volunteers']) }}</div><div class="k">{{ __('Volunteers') }}</div></div>
        <div class="stat @if ($engagement['unread'] > 0) warn @endif">
            <div class="n">{{ number_format($engagement['unread']) }}</div><div class="k">{{ __('Unread messages') }}</div>
        </div>
    </div>

    <div class="grid c2" style="margin-top:16px">
        <div class="card">
            <h2 style="margin-top:0">{{ __('Sections') }}</h2>
            <p class="dim small">{{ __('An empty section renders as a gap on the public site.') }}</p>
            <table>
                @foreach ($sectionStatus as $key => $filled)
                    <tr>
                        <td>
                            <span class="dot {{ $filled ? 'on' : 'off' }}"></span>
                            <a href="{{ route('website.content.edit', $key) }}">{{ ucfirst($key) }}</a>
                        </td>
                        <td class="right-align dim small">{{ $filled ? __('filled') : __('empty') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div>
            <div class="card">
                <h2 style="margin-top:0">{{ __('Latest messages') }}</h2>
                <table>
                    @forelse ($latestMessages as $m)
                        <tr>
                            <td>{{ $m->name }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($m->subject ?: $m->message, 34) }}</td>
                            <td><span class="badge {{ $m->is_read ? 'resolved' : 'reported' }}">{{ $m->status }}</span></td>
                            <td class="dim small">{{ $m->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td class="dim">{{ __('Nothing yet.') }}</td></tr>
                    @endforelse
                </table>
                <p class="small"><a href="{{ route('website.messages.index') }}">{{ __('All messages') }}</a></p>
            </div>

            <div class="card">
                <h2 style="margin-top:0">{{ __('Latest donations') }}</h2>
                <table>
                    @forelse ($latestDonations as $d)
                        <tr>
                            <td>{{ $d->name }}</td>
                            <td class="right-align">{{ number_format((float) $d->amount) }} {{ $d->currency }}</td>
                            <td><span class="badge {{ $d->status === 'approved' ? 'resolved' : 'moderate' }}">{{ $d->status }}</span></td>
                            <td class="dim small">{{ $d->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td class="dim">{{ __('Nothing yet.') }}</td></tr>
                    @endforelse
                </table>
                <p class="small"><a href="{{ route('website.donations.index') }}">{{ __('All donations') }}</a></p>
            </div>
        </div>
    </div>
@endsection
