@extends('layouts.app')
@section('title', $website->exists ? $website->name : __('Add website'))

@section('content')
    <h1>{{ $website->exists ? $website->name : __('Add website') }}</h1>
    <p class="sub"><a href="{{ route('website.sites.index') }}">{{ __('Websites') }}</a></p>

    <form method="POST" action="{{ $website->exists ? route('website.sites.update', $website) : route('website.sites.store') }}" class="card">
        @csrf
        @if ($website->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name', $website->name) }}" required maxlength="120">
            </label>
            <label>
                <span>{{ __('Slug') }}</span>
                <input type="text" name="slug" value="{{ old('slug', $website->slug) }}" required
                       pattern="[a-z0-9-]+" maxlength="60">
            </label>
            <label>
                <span>{{ __('Domain (optional)') }}</span>
                <input type="text" name="domain" value="{{ old('domain', $website->domain) }}" maxlength="190">
            </label>
        </div>

        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="is_active" value="1" style="width:auto"
                   @checked(old('is_active', $website->is_active ?? true))>
            <span style="margin:0">{{ __('Site is live') }}</span>
        </label>

        @if ($website->exists)
            <h2>{{ __('Template and theme') }}</h2>
            <p class="dim small">
                {{ __('Set on the organization profile.') }}
            </p>
            <p>
                <span class="chip">{{ \App\Support\Templates::ALL[$website->templateKey()]['label'] }}</span>
                <span class="chip">{{ \App\Support\ThemeFactory::PRESETS[$website->effectiveTheme()]['label'] }}</span>
                <a class="btn small" href="{{ route('organization.edit') }}">{{ __('Open the organization profile') }}</a>
            </p>
        @else
        <h2>{{ __('Template') }}</h2>
        @foreach (\App\Support\Templates::byCollection() as $collection => $group)
        <h3 class="dim small" style="text-transform:uppercase;letter-spacing:.06em">
            {{ \App\Support\Templates::COLLECTIONS[$collection] }}
        </h3>
        <div class="template-picker" style="margin-bottom:16px">
            @foreach ($group as $key => $t)
                <label>
                    <input type="radio" name="template" value="{{ $key }}"
                           @checked(old('template', $website->template ?? 'template1') === $key)>
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

        <h2>{{ __('Theme') }}</h2>
        <div class="template-picker">
            @foreach ($themes as $key => $t)
                <label>
                    <input type="radio" name="theme" value="{{ $key }}"
                           @checked(old('theme', $website->theme ?? 'fge') === $key)>
                    <strong>{{ $t['label'] }}</strong>
                    <div class="swatches">
                        @foreach ($t['colors'] as $c)
                            <span class="swatch" style="background:{{ $c }}"></span>
                        @endforeach
                    </div>
                    <a class="btn small ghost" style="margin-top:8px"
                       href="{{ route('templates.preview', old('template', $website->template ?? 'template1')) }}?theme={{ $key }}"
                       target="_blank" rel="noopener">
                        {{ __('Preview') }}
                    </a>
                </label>
            @endforeach
        </div>

        <h2>{{ __('Languages') }}</h2>
        @endif
    <p class="dim small">
        {{ __('Translate each section in the content editor.') }}
    </p>
        @php $common = ['en' => 'English', 'sw' => 'Kiswahili', 'fr' => 'Français', 'ar' => 'العربية', 'pt' => 'Português']; @endphp
        <div class="row">
            <label>
                <span>{{ __('Default language') }}</span>
                <select name="default_language" required>
                    @foreach ($common as $code => $name)
                        <option value="{{ $code }}" @selected(old('default_language', $website->default_language ?? 'en') === $code)>{{ $name }} ({{ $code }})</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="row">
            @foreach ($common as $code => $name)
                <label style="display:flex;gap:8px;align-items:center;flex:0 0 auto">
                    <input type="checkbox" name="languages[]" value="{{ $code }}" style="width:auto"
                           @checked(in_array($code, old('languages', $website->languages ?? []) ?: []))>
                    <span style="margin:0">{{ $name }}</span>
                </label>
            @endforeach
        </div>

        <h2>{{ __('Splash screen') }}</h2>
        <p class="dim small">
            {{ __('Shown while the page loads.') }}
        </p>
        <div class="template-picker">
            @foreach ($splashes as $key => $splash)
                <label>
                    <input type="radio" name="splash" value="{{ $key }}"
                           @checked(old('splash', $website->splash ?? 'none') === $key)>
                    <strong>{{ $splash['label'] }}</strong>
                    <div class="desc">{{ $splash['description'] }}</div>
                    @if ($key !== 'none')
                        <a class="btn small ghost" style="margin-top:8px"
                           href="{{ route('templates.preview', $website->templateKey()) }}?splash={{ $key }}"
                           target="_blank" rel="noopener">{{ __('Preview') }}</a>
                    @endif
                </label>
            @endforeach
        </div>

        <div class="row">
            <label>
                <span>{{ __('Show for at most (seconds)') }}</span>
                <input type="number" name="splash_seconds" min="1" max="10"
                       value="{{ old('splash_seconds', $website->splash_seconds ?? 2) }}">
            </label>
            <label>
                <span>{{ __('Splash tagline') }}</span>
                <input type="text" name="splash_tagline" maxlength="120"
                       placeholder="{{ __('defaults to the organization name') }}"
                       value="{{ old('splash_tagline', $website->splash_tagline) }}">
            </label>
        </div>

        <h2>{{ __('Search and social') }}</h2>
        <p class="dim small">
            {{ __('Controls what search engines and social platforms show.') }}
        </p>

        <div class="row">
            <label>
                <span>{{ __('Meta title') }}</span>
                <input type="text" name="meta_title" maxlength="190"
                       placeholder="{{ __('defaults to the site title') }}"
                       value="{{ old('meta_title', $website->meta_title) }}">
            </label>
            <label>
                <span>{{ __('Robots') }}</span>
                <select name="robots" required>
                    @foreach ([
                        'index,follow' => __('Index and follow (normal)'),
                        'noindex,nofollow' => __('Hide from search engines'),
                        'index,nofollow' => __('Index, do not follow links'),
                        'noindex,follow' => __('Do not index, follow links'),
                    ] as $key => $label)
                        <option value="{{ $key }}" @selected(old('robots', $website->robots ?? 'index,follow') === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <label>
            <span>{{ __('Meta description') }}</span>
            <textarea name="meta_description" maxlength="320"
                      placeholder="{{ __('defaults to the About description') }}">{{ old('meta_description', $website->meta_description) }}</textarea>
        </label>

        <div class="row">
            <label>
                <span>{{ __('Keywords') }}</span>
                <input type="text" name="meta_keywords" maxlength="190"
                       value="{{ old('meta_keywords', $website->meta_keywords) }}">
            </label>
            <label>
                <span>{{ __('Canonical URL') }}</span>
                <input type="text" name="canonical_url" maxlength="500"
                       placeholder="{{ __('defaults to the page URL') }}"
                       value="{{ old('canonical_url', $website->canonical_url) }}">
            </label>
        </div>

        <div class="row">
            <label class="image-field" style="flex:2 1 320px">
                <span>{{ __('Open Graph image') }}</span>
                <span class="image-row">
                    <img class="image-thumb" src="{{ $website->og_image ?: '' }}" alt=""
                         @style(['display:none' => ! $website->og_image])>
                    <input type="text" name="og_image" class="image-input" placeholder="/storage/uploads/…"
                           value="{{ old('og_image', $website->og_image) }}">
                    <button type="button" class="btn small ghost image-pick">{{ __('Choose') }}</button>
                </span>
                <span class="dim small">{{ __('Recommended: 1200×630.') }}</span>
            </label>
            <label>
                <span>{{ __('Open Graph type') }}</span>
                <input type="text" name="og_type" maxlength="40"
                       value="{{ old('og_type', $website->og_type ?? 'website') }}">
            </label>
        </div>

        <div class="row">
            <label>
                <span>{{ __('Twitter card') }}</span>
                <select name="twitter_card">
                    <option value="summary_large_image" @selected(old('twitter_card', $website->twitter_card) === 'summary_large_image')>
                        {{ __('Large image') }}
                    </option>
                    <option value="summary" @selected(old('twitter_card', $website->twitter_card) === 'summary')>
                        {{ __('Summary') }}
                    </option>
                </select>
            </label>
            <label>
                <span>{{ __('Twitter handle') }}</span>
                <input type="text" name="twitter_site" maxlength="60" placeholder="@fgetanzania"
                       value="{{ old('twitter_site', $website->twitter_site) }}">
            </label>
        </div>

        @if (! $website->exists)
            <h2>{{ __('Colour overrides') }}</h2>
            <p class="dim small">
                {{ __('Leave blank to use the preset.') }}
            </p>
            <div class="row">
                @foreach (['primary' => __('Primary'), 'secondary' => __('Secondary'), 'tertiary' => __('Tertiary')] as $key => $label)
                    <label>
                        <span>{{ $label }}</span>
                        <input type="text" name="override_{{ $key }}" placeholder="#10b981" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
                               value="{{ old('override_' . $key, $website->theme_overrides[$key] ?? '') }}">
                    </label>
                @endforeach
            </div>
        @endif

        <div class="actions" style="margin-top:14px">
            <button class="btn" type="submit">{{ $website->exists ? __('Save website') : __('Create website') }}</button>
            @if ($website->exists)
                <a class="btn ghost" href="{{ site_url($website, 'home') }}" target="_blank" rel="noopener">{{ __('Preview') }}</a>
            @endif
        </div>
    </form>

    @if ($website->exists && $website->id !== \App\Models\Website::FGE_WEBSITE_ID)
        <form method="POST" action="{{ route('website.sites.destroy', $website) }}" class="card"
              data-confirm="{{ __('Delete :name and everything on it?', ['name' => $website->name]) }}">
            @csrf
            @method('DELETE')
            <h2 style="margin-top:0">{{ __('Danger zone') }}</h2>
            <p class="dim small">{{ __('Deleting a website leaves its content, gallery and users behind as orphans. There is no undo.') }}</p>
            <button class="btn danger" type="submit">{{ __('Delete website') }}</button>
        </form>
    @endif

    <dialog id="image-picker" data-src="{{ route('storage.picker') }}">
        <form method="dialog" class="picker-head">
            <strong>{{ __('Choose an image') }}</strong>
            <span class="spacer"></span>
            <button class="btn small ghost" value="cancel">{{ __('Close') }}</button>
        </form>
        <div class="picker-bar">
            <span class="dim small">{{ __('Collection') }}</span>
            <select id="picker-collection"></select>
            <input type="search" id="picker-search" placeholder="{{ __('Filename') }}">
        </div>
        <div class="media picker-grid" id="picker-grid">
            <p class="dim small" style="padding:12px">{{ __('Loading…') }}</p>
        </div>
    </dialog>
@endsection
