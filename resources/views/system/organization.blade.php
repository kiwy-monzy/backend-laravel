@extends('layouts.app')
@section('title', $organization->name)

@section('content')
    <h1>{{ $organization->name }}</h1>
    <p class="sub">
        <a href="{{ route('system.index') }}">{{ __('System') }}</a> ·
        {{ $organization->slug }} ·
        <span class="chip">{{ $organization->planLabel() }}</span>
    </p>

    <div class="grid c3">
        <div class="stat"><div class="n">{{ $organization->members_count }}</div><div class="k">{{ __('Team members') }}</div></div>
        <div class="stat"><div class="n">{{ $organization->websites_count }}</div><div class="k">{{ __('Websites') }}</div></div>
        <div class="stat @if (! $organization->isActive()) bad @endif">
            <div class="n">{{ $organization->isActive() ? __('Active') : __('Lapsed') }}</div>
            <div class="k">{{ __('Subscription') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('system.organization.update', $organization) }}" class="card">
        @csrf
        @method('PUT')
        <h2 style="margin-top:0">{{ __('Ownership and plan') }}</h2>

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required maxlength="190">
            </label>
            <label>
                <span>{{ __('Owner') }}</span>
                <select name="owner_id">
                    <option value="">{{ __('— unassigned —') }}</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected($organization->owner_id === $owner->id)>
                            {{ $owner->username }} ({{ $owner->roleLabel() }})
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <p class="dim small">
            {{ __('Assigning an owner also seats them as an administrator here — otherwise they would own an organization they could not open.') }}
        </p>

        <div class="row">
            <label>
                <span>{{ __('Plan') }}</span>
                <select name="plan" required>
                    @foreach ($plans as $key => $plan)
                        <option value="{{ $key }}" @selected($organization->plan === $key)>{{ $plan['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Status') }}</span>
                <select name="subscription_status" required>
                    @foreach (['trialing' => __('Trialing'), 'active' => __('Active'), 'past_due' => __('Past due'), 'cancelled' => __('Cancelled')] as $key => $label)
                        <option value="{{ $key }}" @selected($organization->subscription_status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Extend trial by (days)') }}</span>
                <input type="number" name="trial_days" min="0" max="365" placeholder="{{ __('leave blank to keep') }}">
            </label>
        </div>

        <button class="btn" type="submit">{{ __('Save organization') }}</button>
    </form>

    <form method="POST" action="{{ route('system.organization.presentation', $organization) }}" class="card">
        @csrf
        @method('PUT')
        <h2 style="margin-top:0">{{ __('Appearance — template and theme') }}</h2>
        <p class="dim small">{{ __('Only system admins control the look. This is where the template and theme for every website in this organization are set.') }}</p>

        @php $currentTemplate = old('template', $organization->template ?? 'template0'); @endphp
        @foreach (\App\Support\Templates::byCollection() as $collection => $group)
            <h3 class="dim small" style="text-transform:uppercase;letter-spacing:.06em">{{ \App\Support\Templates::COLLECTIONS[$collection] }}</h3>
            <div class="template-picker" style="margin-bottom:16px">
                @foreach ($group as $key => $t)
                    <label>
                        <input type="radio" name="template" value="{{ $key }}" @checked($currentTemplate === $key)>
                        <strong>{{ $t['label'] }}</strong>
                        <div class="desc">{{ $t['description'] }}</div>
                    </label>
                @endforeach
            </div>
        @endforeach

        <div class="template-picker">
            @foreach (\App\Support\ThemeFactory::PRESETS as $key => $t)
                <label>
                    <input type="radio" name="theme" value="{{ $key }}" @checked(old('theme', $organization->theme ?? 'fge') === $key)>
                    <strong>{{ $t['label'] }}</strong>
                    <div class="swatches">
                        @foreach ($t['colors'] as $c)
                            <span class="swatch" style="background:{{ $c }}"></span>
                        @endforeach
                    </div>
                </label>
            @endforeach
        </div>

        <h3>{{ __('Colour overrides') }}</h3>
        <div class="row">
            @foreach (['primary' => __('Primary'), 'secondary' => __('Secondary'), 'tertiary' => __('Tertiary')] as $key => $label)
                <label>
                    <span>{{ $label }}</span>
                    <input type="text" name="override_{{ $key }}" placeholder="#10b981" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})" value="{{ old('override_'.$key, $organization->theme_overrides[$key] ?? '') }}">
                </label>
            @endforeach
        </div>

        <button class="btn" type="submit">{{ __('Save appearance') }}</button>
    </form>

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <h2 style="margin:0">{{ __('Websites in this organization') }}</h2>
            <a class="btn small" href="{{ route('system.organization.website.create', $organization) }}">{{ __('Add website') }}</a>
        </div>
        <table style="margin-top:12px">
            @forelse ($websites as $w)
                <tr>
                    <td><a href="{{ route('system.organization.website.create', $organization) }}" title="{{ __('Edit via Website module') }}">{{ $w->name }}</a><div class="dim small">{{ $w->slug }} · {{ $w->domain ?: '/s/'.$w->slug }}</div></td>
                    <td class="dim small">{{ \App\Support\Templates::ALL[$w->templateKey()]['label'] ?? $w->template }}</td>
                    <td class="dim small"><span class="chip">{{ $w->is_active ? __('active') : __('inactive') }}</span></td>
                    <td class="right-align"><a class="btn small ghost" href="{{ site_url($w, 'home') }}" target="_blank" rel="noopener">{{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td class="dim">{{ __('No websites yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="dim small" style="margin-top:8px">{{ __('Websites are created here by system admins. Owner edits content via Website module, not here.') }}</p>
    </div>

    <form method="POST" action="{{ route('system.organization.modules', $organization) }}" class="card table-wrap">
        @csrf
        @method('PUT')
        <h2 style="margin-top:0">{{ __('Modules this organization may use') }}</h2>
        <p class="dim small">
            {{ __('The top gate. The owner can only hand out to their team what is granted here, so an organization can never grant itself a module. The plan narrows this further, and the owner’s role matrix narrows it again.') }}
        </p>

        <table>
            <tr>
                <th>{{ __('Module') }}</th>
                <th>{{ __('Granted') }}</th>
                <th>{{ __('In plan') }}</th>
                <th>{{ __('Effective') }}</th>
            </tr>
            @foreach ($modules as $slug => $module)
                @php
                    $isGranted = $granted[$slug] ?? true;
                    $inPlan = $organization->planIncludes($slug);
                @endphp
                <tr>
                    <td>
                        <strong>{{ $module['label'] }}</strong>
                        <div class="dim small">{{ $module['description'] ?? '' }}</div>
                    </td>
                    <td>
                        <input type="checkbox" name="modules[{{ $slug }}]" value="1" style="width:auto" @checked($isGranted)>
                    </td>
                    <td>{!! $inPlan ? '<span class="badge resolved">✓</span>' : '<span class="dim">—</span>' !!}</td>
                    <td>
                        @if ($isGranted && $inPlan)
                            <span class="badge resolved">{{ __('available') }}</span>
                        @elseif (! $isGranted)
                            <span class="badge offline">{{ __('not granted') }}</span>
                        @else
                            <span class="badge moderate">{{ __('needs a higher plan') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        <button class="btn" type="submit" style="margin-top:12px">{{ __('Save module grants') }}</button>
    </form>

    <div class="card table-wrap">
        <h2 style="margin-top:0">{{ __('Team') }}</h2>
        <table>
            <tr><th>{{ __('Member') }}</th><th>{{ __('Team role') }}</th><th>{{ __('System tier') }}</th><th>{{ __('Active') }}</th></tr>
            @forelse ($members as $member)
                <tr>
                    <td>{{ $member->user?->username ?? '—' }}<div class="dim small">{{ $member->user?->email }}</div></td>
                    <td>{{ $member->roleLabel() }}</td>
                    <td class="small dim">{{ $member->user?->roleLabel() }}</td>
                    <td>{{ $member->active ? __('Yes') : __('No') }}</td>
                </tr>
            @empty
                <tr><td class="dim">{{ __('Nobody seated yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="dim small">{{ __('The owner manages this list from Organization → Team.') }}</p>
    </div>
@endsection
