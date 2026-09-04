@extends('layouts.app')
@section('title', __('Gallery'))

@section('content')
    <h1>{{ __('Gallery') }}</h1>
    <p class="sub">
        {{ trans_choice(':count image|:count images', $images->count(), ['count' => $images->count()]) }}
        · {{ __('served from Laravel storage') }}
        · <a href="{{ route('storage.collections.show', 'website') }}">{{ __('Storage: website') }}</a>
    </p>
    <p class="dim small">{{ __('Upload to Storage first, then pick here — gallery only shows what you explicitly add.') }}</p>

    <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="row">
            <label>
                <span>{{ __('Add images (upload new)') }}</span>
                <input type="file" name="images[]" accept="image/*" multiple>
            </label>
            <label>
                <span>{{ __('Caption (applied to all)') }}</span>
                <input type="text" name="caption" maxlength="500" placeholder="{{ __('e.g. Graduation 2026') }}">
            </label>
            <div><button class="btn" type="submit">{{ __('Upload & Add to Gallery') }}</button></div>
        </div>

        <div style="margin-top:10px;border-top:1px solid var(--line);padding-top:10px">
            <x-image-field name="photo_url" :label="__('Or pick from Storage → website collection')" />
            <span class="dim small">{{ __('Files must live in Storage first. Picking re-uses the stored file without duplicating bytes.') }}</span>
        </div>
        <div style="margin-top:8px"><button class="btn ghost small" type="submit">{{ __('Add picked from Storage') }}</button></div>
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
        <div class="card table-wrap">
            <table>
                <tr>
                    <th style="width:64px">{{ __('Preview') }}</th>
                    <th>{{ __('Caption') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Shown') }}</th>
                    <th>{{ __('Added') }}</th>
                    <th style="width:220px"></th>
                </tr>
                @foreach ($images as $image)
                    <tr @if($image->disabled) class="dim" @endif>
                        <td><img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy" style="width:56px;height:56px;object-fit:cover;border-radius:6px;display:block"></td>
                        <td>
                            <form method="POST" action="{{ route('website.gallery.update', $image) }}" class="inline-form" style="display:flex;gap:6px;align-items:center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="caption" value="{{ $image->caption }}" placeholder="{{ __('Caption') }}" maxlength="500" style="flex:1;min-width:140px">
                                <input type="hidden" name="disabled" value="0">
                                <label class="small" style="display:flex;gap:4px;align-items:center;white-space:nowrap">
                                    <input type="checkbox" name="disabled" value="1" style="width:auto" @checked($image->disabled)>
                                    <span>{{ __('Hidden') }}</span>
                                </label>
                                <button class="btn small" type="submit">{{ __('Save') }}</button>
                            </form>
                        </td>
                        <td class="dim small" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ basename($image->url) }}</td>
                        <td>
                            @if($image->disabled)
                                <span class="badge offline">{{ __('hidden') }}</span>
                            @else
                                <span class="badge resolved">{{ __('active') }}</span>
                            @endif
                        </td>
                        <td class="dim small">{{ $image->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="right-align" style="white-space:nowrap">
                            <a class="btn small ghost" href="{{ $image->url }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
                            <form method="POST" action="{{ route('website.gallery.destroy', $image) }}" class="inline-form" data-confirm="{{ __('Delete this image from gallery? File stays in Storage.') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <p class="dim small" style="margin-top:6px">{{ __('Storage and Gallery are separate: deleting from Gallery does not delete the file; delete in Storage to free bytes (blocked while Gallery still uses it).') }}</p>
    @endif
@endsection
