<p class="nav" style="margin-bottom:16px">
    <a href="{{ route('organization.edit') }}" @class(['on' => request()->routeIs('organization.edit')])>{{ __('Profile') }}</a>
    <a href="{{ route('organization.team') }}" @class(['on' => request()->routeIs('organization.team')])>{{ __('Team') }}</a>
    @if (auth()->user()?->isSystemAdmin())
        <a href="{{ route('organization.access') }}" @class(['on' => request()->routeIs('organization.access')])>{{ __('Access') }}</a>
        <a href="{{ route('organization.subscription') }}" @class(['on' => request()->routeIs('organization.subscription')])>{{ __('Subscription') }}</a>
    @endif
</p>
