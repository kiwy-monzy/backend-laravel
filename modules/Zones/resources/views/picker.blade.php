@php
    $definition = config('zones.zonable.' . $zoneKind);
    $available = \Modules\Zones\Models\Zone::where('organization_id', $record->organization_id ?? $record->id)
        ->active()->orderBy('name')->get();
    $held = $record->exists ? $record->zones->pluck('id')->all() : [];
@endphp

@if ($record->exists)
    <div class="card" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __($definition['label'] ?? 'Zones') }}</h2>
        <p class="dim small">{{ __($definition['hint'] ?? '') }}</p>

        @if ($available->isEmpty())
            <p class="dim">
                {{ __('No zones have been drawn yet.') }}
                <a href="{{ route('zones.records.create') }}">{{ __('Draw one') }}</a>.
            </p>
        @else
            <form method="POST" action="{{ route('zones.attach', ['kind' => $zoneKind, 'record' => $record->getKey()]) }}">
                @csrf @method('PUT')

                <div class="row" style="gap:10px">
                    @foreach ($available as $zone)
                        <label style="display:flex;gap:8px;align-items:center;flex-basis:220px">
                            <input type="{{ method_exists($record, 'hasSingleZone') && $record->hasSingleZone() ? 'radio' : 'checkbox' }}"
                                   name="zones[]" value="{{ $zone->id }}" style="width:auto"
                                   @checked(in_array($zone->id, $held, true))>
                            <span style="margin:0">
                                <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:{{ $zone->colour }};margin-right:5px"></span>
                                {{ $zone->name }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <button class="btn" type="submit" style="margin-top:10px">{{ __('Save zones') }}</button>
            </form>
        @endif
    </div>
@endif
