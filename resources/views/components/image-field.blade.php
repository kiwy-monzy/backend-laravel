@props([
    'name',
    'label' => null,
    'value' => null,
    'help' => null,
    'placeholder' => '/storage/uploads/…',
])

{{--
    An image chosen from the organization's own storage.

    Every image in the admin — a user's avatar, a team member's portrait, a site
    logo — is a file the organization already holds, so the field offers the
    library rather than asking for a URL somebody has to find elsewhere. Typing
    a path still works; the picker is the easy way, not the only way.

    The markup matches what `admin.js` already drives for the content editor:
    an `.image-row` holding a thumbnail, the real input, and the button that
    opens the shared dialog. Reusing those hooks is why this needs no JavaScript
    of its own.
--}}
<label class="image-field">
    @if ($label)<span>{{ $label }}</span>@endif

    <span class="image-row">
        <img class="image-thumb" src="{{ $value ?: '' }}" alt=""
             @style(['display:none' => ! $value])>
        <input type="text" name="{{ $name }}" value="{{ $value }}"
               placeholder="{{ $placeholder }}" class="image-input">
        <button type="button" class="btn small ghost image-pick">{{ __('Choose') }}</button>
    </span>

    @if ($help)<span class="dim small">{{ $help }}</span>@endif
</label>
