@extends('layouts.app')
@section('title', __('Gallery'))

@section('content')
    <h1>{{ __('Gallery') }}</h1>
    <p class="sub">
        {{ trans_choice(':count image|:count images', $images->count(), ['count' => $images->count()]) }}
        · {{ __('served from Laravel storage') }}
    </p>

    <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="row">
            <label>
                <span>{{ __('Add images') }}</span>
                <input type="file" name="images[]" accept="image/*" multiple>
            </label>
            <label>
                <span>{{ __('Caption (applied to all)') }}</span>
                <input type="text" name="caption" maxlength="500">
            </label>
            <div><button class="btn" type="submit">{{ __('Add') }}</button></div>
        </div>

        <div style="margin-top:10px;border-top:1px solid var(--line);padding-top:10px">
            <x-image-field name="photo_url" :label="__('Or choose from your organization’s files')" />
        </div>
    </form>

    <x-image-picker />

    @if ($images->isNotEmpty())
        <div class="card" style="padding:10px">
            <div data-grid
                 data-src="{{ route('website.gallery.data') }}"
                 data-columns='@json($gridColumns)'
                 data-per-page="100"
                 data-empty="{{ __('No images yet.') }}"></div>
        </div>
    @endif

    @if ($images->isEmpty())
        <p class="dim">{{ __('No images yet.') }}</p>
    @else
        <h2>{{ __('Edit captions') }}</h2>
        <div class="media">
            @foreach ($images as $image)
                <div class="tile @if ($image->disabled) off @endif">
                    <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy">
                    <div class="tile-body">
                        <form method="POST" action="{{ route('website.gallery.update', $image) }}" style="display:block">
                            @csrf
                            @method('PUT')
                            <input type="text" name="caption" value="{{ $image->caption }}"
                                   placeholder="{{ __('Caption') }}" maxlength="500">
                            <label class="small" style="display:flex;gap:6px;align-items:center;margin:8px 0">
                                <input type="checkbox" name="disabled" value="1" style="width:auto"
                                       @checked($image->disabled)>
                                <span style="margin:0">{{ __('Hidden') }}</span>
                            </label>
                            <button class="btn small" type="submit">{{ __('Save') }}</button>
                        </form>

                        <form method="POST" action="{{ route('website.gallery.destroy', $image) }}"
                              data-confirm="{{ __('Delete this image?') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
