@php
    $type = $sub['type'];
    $wide = in_array($type, ['textarea'], true);
@endphp

@if ($type === 'checkbox')
    <label style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="{{ $inputName }}" value="0">
        <input type="checkbox" name="{{ $inputName }}" value="1" style="width:auto" @checked($subValue)>
        <span style="margin:0">{{ $sub['label'] }}</span>
    </label>

@elseif ($type === 'textarea')
    <label style="flex-basis:100%">
        <span>{{ $sub['label'] }}</span>
        <textarea name="{{ $inputName }}">{{ $subValue }}</textarea>
        @if (! empty($sub['help']))<span class="dim small">{{ $sub['help'] }}</span>@endif
    </label>

@elseif ($type === 'select')
    <label>
        <span>{{ $sub['label'] }}</span>
        <select name="{{ $inputName }}">
            @foreach ($sub['options'] as $key => $label)
                <option value="{{ $key }}" @selected((string) $subValue === (string) $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

@elseif ($type === 'image')
    <label class="image-field">
        <span>{{ $sub['label'] }}</span>
        <span class="image-row">
            <img class="image-thumb" src="{{ $subValue ?: '' }}" alt=""
                 @style(['display:none' => ! $subValue])>
            <input type="text" name="{{ $inputName }}" value="{{ $subValue }}"
                   placeholder="/storage/uploads/…" class="image-input">
            <button type="button" class="btn small ghost image-pick">{{ __('Choose') }}</button>
        </span>
        @if (! empty($sub['help']))<span class="dim small">{{ $sub['help'] }}</span>@endif
    </label>

@elseif ($type === 'color')
    <label>
        <span>{{ $sub['label'] }}</span>
        <span class="image-row">
            <input type="color" value="{{ $subValue ?: '#10b981' }}" class="color-swatch" style="width:44px;padding:2px">
            <input type="text" name="{{ $inputName }}" value="{{ $subValue }}" placeholder="#10b981" class="color-text">
        </span>
    </label>

@else
    <label>
        <span>{{ $sub['label'] }}</span>
        <input type="{{ in_array($type, ['email', 'url', 'date']) ? $type : 'text' }}"
               name="{{ $inputName }}" value="{{ $subValue }}">
        @if (! empty($sub['help']))<span class="dim small">{{ $sub['help'] }}</span>@endif
    </label>
@endif
