@extends('layouts.app')
@section('title', $item->exists ? $item->name : __('Add item'))

@section('content')
    <h1>{{ $item->exists ? $item->name : __('Add item') }}</h1>
    <p class="sub"><a href="{{ route('invoicing.items.index') }}">{{ __('Items') }}</a></p>

    <form method="POST"
          action="{{ $item->exists ? route('invoicing.items.update', $item) : route('invoicing.items.store') }}"
          class="card">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="190">
            </label>
            <label>
                <span>{{ __('SKU') }}</span>
                <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" maxlength="60">
            </label>
            <label>
                <span>{{ __('Type') }}</span>
                <select name="item_type" required>
                    @foreach (\Modules\Invoicing\Models\Item::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('item_type', $item->item_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('Unit') }}</span>
                <input type="text" name="unit" value="{{ old('unit', $item->unit) }}" maxlength="30">
            </label>
        </div>

        <label>
            <span>{{ __('Product category') }}</span>
            @php $cats = \App\Support\Taxonomy::options(\App\Support\Taxonomy::GOOGLE); @endphp
            <input type="text" list="google-taxonomy" id="cat-search"
                   value="{{ $item->google_category ? ($cats[$item->google_category] ?? '') : '' }}"
                   placeholder="{{ __('Start typing — e.g. Computers, Cement, Plumbing') }}">
            <input type="hidden" name="google_category" id="cat-id" value="{{ old('google_category', $item->google_category) }}">
            <datalist id="google-taxonomy">
                @foreach ($cats as $id => $path)
                    <option data-id="{{ $id }}" value="{{ $path }}">
                @endforeach
            </datalist>
            <span class="dim small">{{ __('Google product taxonomy — used by marketplace feeds and customs. Optional.') }}</span>
        </label>

        <div class="row">
            <label>
                <span>{{ __('Selling rate') }} ({{ $organization?->currency }})</span>
                <input type="number" step="0.01" min="0" name="rate"
                       value="{{ old('rate', number_format($item->rate_minor / 100, 2, '.', '')) }}" required>
            </label>
            <label>
                <span>{{ __('Purchase rate') }}</span>
                <input type="number" step="0.01" min="0" name="purchase_rate"
                       value="{{ old('purchase_rate', number_format($item->purchase_rate_minor / 100, 2, '.', '')) }}">
            </label>
            <label>
                <span>{{ __('Tax %') }}</span>
                <input type="number" step="0.001" min="0" max="100" name="tax_percent"
                       value="{{ old('tax_percent', $item->tax_percent ?? 0) }}">
            </label>
        </div>

        <label>
            <span>{{ __('Description') }}</span>
            <textarea name="description" maxlength="2000">{{ old('description', $item->description) }}</textarea>
        </label>

        <div class="row">
            <label style="display:flex;gap:8px;align-items:center">
                <input type="checkbox" name="track_inventory" value="1" style="width:auto"
                       @checked(old('track_inventory', $item->track_inventory))>
                <span style="margin:0">{{ __('Track stock (goods only)') }}</span>
            </label>
            <label>
                <span>{{ __('Stock on hand') }}</span>
                <input type="number" step="0.001" name="stock_on_hand" value="{{ old('stock_on_hand', $item->stock_on_hand ?? 0) }}">
            </label>
            <label style="display:flex;gap:8px;align-items:center">
                <input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $item->active ?? true))>
                <span style="margin:0">{{ __('Active') }}</span>
            </label>
        </div>

        <button class="btn" type="submit">{{ $item->exists ? __('Save item') : __('Create item') }}</button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var search = document.getElementById('cat-search');
                var hidden = document.getElementById('cat-id');
                var list = document.getElementById('google-taxonomy');
                if (!search || !hidden || !list) return;

                search.addEventListener('change', function () {
                    var match = Array.from(list.options).find(function (o) { return o.value === search.value; });
                    hidden.value = match ? match.dataset.id : '';
                });
            })();
        </script>
    @endpush
@endsection
