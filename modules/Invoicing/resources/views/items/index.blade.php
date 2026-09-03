@extends('layouts.app')
@section('title', __('Items'))

@section('content')
    <h1>{{ __('Items') }}</h1>
    <p class="sub">{{ __('The goods and services you bill for.') }}</p>

    <form method="GET" action="{{ route('invoicing.items.index') }}" class="card">
        <div class="row">
            <label>
                <span>{{ __('Search') }}</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Name or SKU') }}">
            </label>
            <div class="actions">
                <button class="btn" type="submit">{{ __('Search') }}</button>
                @if ($mayAdd)
                    <a class="btn ghost" href="{{ route('invoicing.items.create') }}">{{ __('Add item') }}</a>
                @endif
            </div>
        </div>
    </form>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('SKU') }}</th>
                <th>{{ __('Type') }}</th>
                <th class="right-align">{{ __('Rate') }}</th>
                <th class="right-align">{{ __('Tax') }}</th>
                <th class="right-align">{{ __('Stock') }}</th>
                <th></th>
            </tr>
            @forelse ($items as $item)
                <tr @class(['dim' => ! $item->active])>
                    <td><a href="{{ route('invoicing.items.edit', $item) }}">{{ $item->name }}</a></td>
                    <td class="small dim">{{ $item->sku ?: '—' }}</td>
                    <td><span class="badge">{{ \Modules\Invoicing\Models\Item::TYPES[$item->item_type] }}</span></td>
                    <td class="right-align">{{ \Modules\Invoicing\Models\Money::format($item->rate_minor, $organization?->currency ?? 'TZS') }}</td>
                    <td class="right-align">{{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%</td>
                    <td class="right-align">{{ $item->track_inventory ? rtrim(rtrim(number_format($item->stock_on_hand, 3), '0'), '.') : '—' }}</td>
                    <td class="right-align">
                        @if ($mayDelete)
                            <form method="POST" action="{{ route('invoicing.items.destroy', $item) }}" class="inline-form"
                                  data-confirm="{{ __('Delete :name?', ['name' => $item->name]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="dim">{{ __('No items yet.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $items->links() }}
@endsection
