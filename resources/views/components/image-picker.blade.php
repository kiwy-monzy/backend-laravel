{{--
    The shared image-picker dialog.

    Its contents come from /admin/m/storage/picker, so it only ever offers files
    belonging to *this* organization, from collections the owner has marked
    selectable. Include it once on any page that has an `<x-image-field>`.

    A page whose user cannot open the Storage module gets no dialog — the field
    still accepts a typed path, and nothing offers a library they may not read.
--}}
@if (\Illuminate\Support\Facades\Route::has('storage.picker') && auth()->user()?->allowedModule('storage'))
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
@endif
