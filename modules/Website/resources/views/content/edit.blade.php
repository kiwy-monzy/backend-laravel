@extends('layouts.app')
@section('title', $label)
@section('body_class', 'content-editor')

@php
    use Illuminate\Support\Arr;
    $previewUrl = route('templates.preview', $site?->templateKey() ?? 'template1') . '?page=' . $previewPage . '&lang=' . $locale;
@endphp

@section('content')
    <h1>{{ $label }}</h1>
    <p class="sub">
        <a href="{{ route('website.content.index') }}">{{ __('Content') }}</a> ·
        {{ $site?->name }} ·
        <code>{{ $section }}</code>
        @if ($site)
            · <span class="chip">{{ \App\Support\Templates::ALL[$site->templateKey()]['label'] }}</span>
        @endif
    </p>

    @if (\App\Support\ContentSchema::isManaged($section))
        <div class="card">
            <p>{{ __('Gallery images are managed on their own page.') }}</p>
            <a class="btn" href="{{ route('website.gallery.index') }}">{{ __('Open the gallery') }}</a>
        </div>
    @endif

    @if (count($languages) > 1)
        <p class="nav lang-picker" style="margin-bottom:10px">
            <span class="dim small" style="margin-right:6px">{{ __('Language') }}</span>
            @foreach ($languages as $lng)
                <a href="{{ route('website.content.edit', [$section, 'lang' => $lng, 'mode' => $mode]) }}"
                   @class(['on' => $locale === $lng])>{{ strtoupper($lng) }}</a>
            @endforeach
            @if ($locale !== ($site->default_language ?? 'en'))
                <span class="dim small">{{ __('Blank fields fall back to :d on the public site.', ['d' => strtoupper($site->default_language ?? 'en')]) }}</span>
            @endif
        </p>
    @endif

    <p class="nav" style="margin-bottom:14px">
        @if (\App\Support\ContentSchema::hasSchema($section))
            <a href="{{ route('website.content.edit', $section) }}" @class(['on' => $mode === 'form'])>{{ __('Form') }}</a>
        @endif
        <a href="{{ route('website.content.edit', [$section, 'mode' => 'json']) }}" @class(['on' => $mode === 'json'])>{{ __('Raw JSON') }}</a>
    </p>

    <div class="editor-split">
        <div class="editor-pane">
            @if ($mode === 'json')
                <form method="POST" action="{{ route('website.content.update', $section) }}" class="card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="editor" value="json">
                    <input type="hidden" name="lang" value="{{ $locale }}">

                    <label>
                        <span>{{ __('Section data (JSON)') }}</span>
                        <textarea class="json-editor" name="data" spellcheck="false"
                                  data-status="#json-status">{{ old('data', $json) }}</textarea>
                    </label>
                    <div class="json-status" id="json-status"></div>

                    <div class="actions" style="margin-top:12px">
                        <button class="btn" type="submit">{{ __('Save section') }}</button>
                        <button class="btn ghost" type="button" data-json-format>{{ __('Reformat') }}</button>
                    </div>

                    <p class="dim small" style="margin-top:10px">
                        {{ __('Data: URLs are auto-uploaded to storage.') }}
                    </p>
                </form>
            @else
                <form method="POST" action="{{ route('website.content.update', $section) }}" class="card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="editor" value="form">
                    <input type="hidden" name="lang" value="{{ $locale }}">

                    @foreach ($schema as $field)
                        @include('website::content._field', [
                            'field' => $field,
                            'value' => Arr::get($data, $field['name']),
                            'data' => $data,
                        ])
                    @endforeach

                    <button class="btn" type="submit">{{ __('Save section') }}</button>
                    <p class="dim small" style="margin-top:10px">
                        {{ __('Use Raw JSON for fields not shown here.') }}
                    </p>
                </form>
            @endif
        </div>

        <div class="editor-preview">
            <div class="preview-bar">
                <strong>{{ __('Preview') }}</strong>
                <span class="dim small">{{ $previewPage }}</span>
                <span class="spacer"></span>
                <button class="btn small ghost" type="button" data-preview-width="375">{{ __('Phone') }}</button>
                <button class="btn small ghost" type="button" data-preview-width="768">{{ __('Tablet') }}</button>
                <button class="btn small ghost" type="button" data-preview-width="0">{{ __('Full') }}</button>
                <button class="btn small" type="button" id="preview-reload">{{ __('Reload') }}</button>
                <a class="btn small ghost" href="{{ $previewUrl }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
            </div>
            <iframe id="content-preview" src="{{ $previewUrl }}" title="{{ __('Site preview') }}"></iframe>
        </div>
    </div>

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
            <span class="spacer"></span>
            <a class="btn small ghost" href="{{ route('storage.index') }}" target="_blank" rel="noopener">
                {{ __('Manage storage') }}
            </a>
        </div>

        <div class="media picker-grid" id="picker-grid">
            <p class="dim small" style="padding:12px">{{ __('Loading…') }}</p>
        </div>
    </dialog>

@endsection
