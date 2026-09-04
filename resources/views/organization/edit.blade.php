@extends('layouts.app')
@section('title', __('Organization'))

@section('content')
    <h1>{{ $organization->name }}</h1>
    <p class="sub">
        {{ __('The tenant that owns your websites, people and records.') }}
        · <span class="chip">{{ $organization->planLabel() }}</span>
    </p>

    @include('organization._tabs')

    <form method="POST" action="{{ route('organization.update') }}" class="card">
        @csrf
        @method('PUT')

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required maxlength="190" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Slug') }}</span>
                <input type="text" value="{{ $organization->slug }}" disabled>
            </label>
        </div>

        <h2>{{ __('Contact') }}</h2>
        <p class="dim small">{{ __('Shown on invoices and quoted to your customers.') }}</p>
        <div class="row">
            <label>
                <span>{{ __('Email') }}</span>
                <input type="email" name="email" value="{{ old('email', $organization->email) }}" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Phone') }}</span>
                <input type="tel" name="phone" value="{{ old('phone', $organization->phone) }}" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Address') }}</span>
                <input type="text" name="address" value="{{ old('address', $organization->address) }}" @disabled(! $canManage)>
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Country') }}</span>
                <input type="text" name="country" value="{{ old('country', $organization->country) }}" maxlength="8" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Currency') }}</span>
                <input type="text" name="currency" value="{{ old('currency', $organization->currency) }}" maxlength="8" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Logo URL') }}</span>
                <input type="text" name="logo_url" value="{{ old('logo_url', $organization->general['logo_url'] ?? $organization->logo_url ?? '') }}" @disabled(! $canManage)>
            </label>
        </div>

        <h2>{{ __('Public website') }}</h2>
        <p class="dim small">{{ __('The identity the public site and its API are built from — the same data every template renders as General.') }}</p>
        <div class="row">
            <label>
                <span>{{ __('Site name') }}</span>
                <input type="text" name="site_name" value="{{ old('site_name', $organization->general['site_name'] ?? '') }}" maxlength="190" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Site title') }}</span>
                <input type="text" name="site_title" value="{{ old('site_title', $organization->general['site_title'] ?? '') }}" maxlength="190" @disabled(! $canManage)>
            </label>
            <label>
                <span>{{ __('Logo text') }}</span>
                <input type="text" name="logo_text" value="{{ old('logo_text', $organization->general['logo_text'] ?? '') }}" maxlength="190" @disabled(! $canManage)>
            </label>
        </div>

        <div class="row">
            @foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network)
                <label>
                    <span>{{ ucfirst($network) }}</span>
                    <input type="url" name="social_links[{{ $network }}]"
                           value="{{ old("social_links.$network", $organization->general['social_links'][$network] ?? '') }}"
                           @disabled(! $canManage)>
                </label>
            @endforeach
        </div>

        <fieldset class="repeat" @disabled(! $canManage)>
            <legend>{{ __('Show these sections') }}</legend>
            <div class="row">
                @foreach (['hero', 'about', 'projects', 'services', 'achievements', 'team', 'gallery', 'volunteer', 'donate', 'footer'] as $key)
                    <label style="display:flex;gap:8px;align-items:center;flex:0 0 auto">
                        <input type="hidden" name="visibility[{{ $key }}]" value="0">
                        <input type="checkbox" name="visibility[{{ $key }}]" value="1" style="width:auto"
                               @checked(old("visibility.$key", $organization->general['visibility'][$key] ?? true))
                               @disabled(! $canManage)>
                        <span style="margin:0">{{ ucfirst($key) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if (auth()->user()?->isSystemAdmin())
            <h2>{{ __('Template and theme') }}</h2>
            <p class="dim small">
                {{ __('The look every website this organization owns renders in. Preview first — switching a live site to see what a template looks like is not a thing anyone should have to do.') }}
            </p>
            @php $currentTemplate = old('template', $organization->template ?? 'template1'); @endphp
            @foreach (\App\Support\Templates::byCollection() as $collection => $group)
                <h3 class="dim small" style="text-transform:uppercase;letter-spacing:.06em">
                    {{ \App\Support\Templates::COLLECTIONS[$collection] }}
                </h3>
                <div class="template-picker" style="margin-bottom:16px">
                    @foreach ($group as $key => $t)
                        <label>
                            <input type="radio" name="template" value="{{ $key }}"
                                   @checked($currentTemplate === $key) @disabled(! $canManage)>
                            <strong>{{ $t['label'] }}</strong>
                            <div class="desc">{{ $t['description'] }}</div>
                            <a class="btn small ghost" style="margin-top:8px"
                               href="{{ route('templates.preview', $key) }}" target="_blank" rel="noopener">
                                {{ __('Preview') }}
                            </a>
                        </label>
                    @endforeach
                </div>
            @endforeach

            <div class="template-picker">
                @foreach (\App\Support\ThemeFactory::PRESETS as $key => $t)
                    <label>
                        <input type="radio" name="theme" value="{{ $key }}"
                               @checked(old('theme', $organization->theme ?? 'fge') === $key) @disabled(! $canManage)>
                        <strong>{{ $t['label'] }}</strong>
                        <div class="swatches">
                            @foreach ($t['colors'] as $c)
                                <span class="swatch" style="background:{{ $c }}"></span>
                            @endforeach
                        </div>
                        <a class="btn small ghost" style="margin-top:8px"
                           href="{{ route('templates.preview', $currentTemplate) }}?theme={{ $key }}"
                           target="_blank" rel="noopener">
                            {{ __('Preview') }}
                        </a>
                    </label>
                @endforeach
            </div>

            <h3>{{ __('Colour overrides') }}</h3>
            <p class="dim small">
                {{ __('Leave blank to use the palette. Overrides survive a palette change, so you can try presets without losing a hand-picked brand colour.') }}
            </p>
            <div class="row">
                @foreach (['primary' => __('Primary'), 'secondary' => __('Secondary'), 'tertiary' => __('Tertiary')] as $key => $label)
                    <label>
                        <span>{{ $label }}</span>
                        <input type="text" name="override_{{ $key }}" placeholder="#10b981"
                               pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
                               value="{{ old('override_' . $key, $organization->theme_overrides[$key] ?? '') }}"
                               @disabled(! $canManage)>
                    </label>
                @endforeach
            </div>
        @else
            <div class="card dim" style="border:1px dashed #d1d5db">
                <strong>{{ __('Template and theme') }}</strong>
                <p class="dim small" style="margin:4px 0 0">
                    {{ __('Only a system admin can change the template and theme. Ask an admin on :route.', ['route' => route('system.organization', $organization->id)]) }}
                    <br>
                    {{ __('Current: :template — :theme', ['template' => \App\Support\Templates::ALL[$organization->templateKey()]['label'] ?? $organization->templateKey(), 'theme' => \App\Support\ThemeFactory::PRESETS[$organization->effectiveTheme()]['label'] ?? $organization->effectiveTheme()]) }}
                </p>
            </div>
        @endif

        @if ($canManage)
            <button class="btn" type="submit">{{ __('Save organization') }}</button>
        @else
            <p class="dim small">{{ __('Only an organization administrator can change these.') }}</p>
        @endif
    </form>

    <div class="card">
        <h2 style="margin-top:0">{{ __('Websites') }}</h2>
        <table>
            @forelse ($organization->websites as $w)
                <tr>
                    <td><a href="{{ site_url($w, 'home') }}" target="_blank" rel="noopener">{{ $w->name }}</a></td>
                    <td class="dim small">{{ $w->domain ?: '/s/' . $w->slug }}</td>
                    <td class="dim small">{{ \App\Support\Templates::ALL[$w->templateKey()]['label'] }}</td>
                </tr>
            @empty
                <tr><td class="dim">{{ __('No websites yet.') }}</td></tr>
            @endforelse
        </table>
    </div>
@endsection
