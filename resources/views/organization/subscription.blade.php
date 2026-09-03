@extends('layouts.app')
@section('title', __('Subscription'))

@section('content')
    <h1>{{ __('Subscription') }}</h1>
    <p class="sub">{{ $organization->name }}</p>

    @include('organization._tabs')

    <div class="grid c3">
        <div class="stat">
            <div class="n">{{ $organization->planLabel() }}</div>
            <div class="k">{{ __('Current plan') }}</div>
        </div>
        <div class="stat @if (! $organization->isActive()) bad @endif">
            <div class="n">{{ $organization->isActive() ? __('Active') : __('Lapsed') }}</div>
            <div class="k">{{ __('Status') }}</div>
        </div>
        <div class="stat @if (($organization->trialDaysLeft() ?? 99) < 4) warn @endif">
            <div class="n">
                @if ($organization->onTrial())
                    {{ $organization->trialDaysLeft() }}
                @else
                    —
                @endif
            </div>
            <div class="k">{{ __('Trial days left') }}</div>
        </div>
    </div>

    @unless ($organization->isActive())
        <div class="flash bad" style="margin-top:16px">
            {{ __('This organization is read-only until the plan is renewed. Your data is untouched — only changes are refused.') }}
        </div>
    @endunless

    <form method="POST" action="{{ route('organization.subscription.update') }}" class="card">
        @csrf
        @method('PUT')

        <h2 style="margin-top:0">{{ __('Plans') }}</h2>
        <p class="dim small">
            {{ __('Changing the plan here records it. No payment is taken — a payment processor is not wired up, and a checkout that pretended otherwise would be worse than none.') }}
        </p>

        <div class="template-picker">
            @foreach ($plans as $key => $plan)
                <label>
                    <input type="radio" name="plan" value="{{ $key }}"
                           @checked($organization->plan === $key) @disabled(! $canManage)>
                    <strong>{{ $plan['label'] }}</strong>
                    <div class="desc">{{ $plan['tagline'] }}</div>
                    <div class="dim small" style="margin-top:6px">
                        @if ($plan['price_minor'] === 0)
                            {{ __('Free') }}
                        @else
                            ${{ number_format($plan['price_minor'] / 100, 2) }} / {{ __('month') }}
                        @endif
                    </div>
                    <div class="dim small" style="margin-top:6px">
                        @php $covered = \App\Models\Organization::planModules($key); @endphp
                        {{ trans_choice(':count module|:count modules', count($covered), ['count' => count($covered)]) }}
                        <div style="margin-top:2px">
                            {{ \Illuminate\Support\Str::limit(implode(', ', array_map(fn ($m) => \App\Support\Modules::label($m), $covered)), 90) }}
                        </div>
                    </div>
                </label>
            @endforeach
        </div>

        @if ($canManage)
            <button class="btn" type="submit" style="margin-top:14px">{{ __('Change plan') }}</button>
        @endif
    </form>

    <div class="card">
        <h2 style="margin-top:0">{{ __('Modules on this plan') }}</h2>
        <table>
            @forelse ($modules as $slug => $module)
                <tr>
                    <td>
                        <span class="dot {{ $organization->planIncludes($slug) ? 'on' : 'off' }}"></span>
                        {{ $module['label'] }}
                    </td>
                    <td class="dim small">{{ $module['description'] ?? '' }}</td>
                    <td class="right-align">
                        {{ $organization->planIncludes($slug) ? __('included') : __('not included') }}
                    </td>
                </tr>
            @empty
                <tr><td class="dim">{{ __('No modules installed.') }}</td></tr>
            @endforelse
        </table>
    </div>
@endsection
