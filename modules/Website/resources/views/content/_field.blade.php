@php
    use Illuminate\Support\Arr;
    $name = $field['name'];
    $type = $field['type'];
    // Dotted schema names become bracketed input names: `social_links.facebook`
    // posts as `social_links[facebook]`, which is what Arr::set reads back.
    $input = str_contains($name, '.')
        ? preg_replace('/\.([^.]+)/', '[$1]', $name)
        : $name;
@endphp

@if ($type === 'repeat')
    @php $rows = collect($value ?: [])->values(); @endphp

    <fieldset class="repeat" data-repeat="{{ $name }}">
        <legend>{{ $field['label'] }}</legend>

        <div class="repeat-rows">
            @foreach ($rows as $i => $row)
                <div class="repeat-row">
                    <input type="hidden" name="{{ $name }}[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                    <div class="row">
                        @foreach ($field['fields'] as $sub)
                            @include('website::content._input', [
                                'sub' => $sub,
                                'inputName' => $name . '[' . $i . '][' . $sub['name'] . ']',
                                'subValue' => $row[$sub['name']] ?? null,
                            ])
                        @endforeach
                    </div>
                    <button type="button" class="btn small ghost repeat-remove">{{ __('Remove') }}</button>
                </div>
            @endforeach
        </div>

        <template class="repeat-template">
            <div class="repeat-row">
                <input type="hidden" name="{{ $name }}[__i__][id]" value="">
                <div class="row">
                    @foreach ($field['fields'] as $sub)
                        @include('website::content._input', [
                            'sub' => $sub,
                            'inputName' => $name . '[__i__][' . $sub['name'] . ']',
                            'subValue' => null,
                        ])
                    @endforeach
                </div>
                <button type="button" class="btn small ghost repeat-remove">{{ __('Remove') }}</button>
            </div>
        </template>

        <button type="button" class="btn small repeat-add" data-next="{{ $rows->count() }}">
            {{ __('Add :thing', ['thing' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::singular($field['label']))]) }}
        </button>
    </fieldset>

@elseif ($type === 'toggles')
    <fieldset class="repeat">
        <legend>{{ $field['label'] }}</legend>
        <div class="row">
            @foreach ($field['keys'] as $key)
                <label style="display:flex;gap:8px;align-items:center;flex:0 0 auto">
                    <input type="checkbox" name="{{ $name }}[{{ $key }}]" value="1" style="width:auto"
                           @checked(Arr::get($data, "$name.$key", true))>
                    <span style="margin:0">{{ ucfirst($key) }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

@else
    @include('website::content._input', ['sub' => $field, 'inputName' => $input, 'subValue' => $value])
@endif
