@extends('layouts.app')
@section('title', \Modules\Invoicing\Models\Document::TYPES[$type] ?? __('Documents'))

@php
    $rb = $routeBase ?? 'invoicing.invoices';
    $formBase = $formRouteBase ?? 'invoicing.invoices';
    $types = $types ?? \Modules\Invoicing\Models\Document::TYPES;

    $doc = \Modules\Invoicing\Models\Document::class;
    $partyLabel = $doc::partyLabelFor($type);
    $dueLabel = $doc::dueLabelFor($type);
    $showsBalance = $doc::showsBalanceFor($type);
    $totalLabel = $doc::totalLabelFor($type);
@endphp

@section('content')
    <h1>{{ \Illuminate\Support\Str::plural(\Modules\Invoicing\Models\Document::TYPES[$type] ?? 'Document') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <p class="nav" style="margin-bottom:14px">
        @foreach ($types as $key => $label)
            <a href="{{ route($rb . '.index', ['type' => $key]) }}" @class(['on' => $type === $key])>
                {{ \Illuminate\Support\Str::plural($label) }}
            </a>
        @endforeach
        <span class="spacer"></span>
        @foreach (\Modules\Invoicing\Models\Document::STATUSES as $key => $label)
            <a href="{{ route($rb . '.index', ['type' => $type, 'status' => $key]) }}"
               @class(['on' => $status === $key])>{{ $label }}</a>
        @endforeach
    </p>

    @if ($mayAdd)
        <p>
            <a class="btn" href="{{ route($formBase . '.create', ['type' => $type]) }}">
                {{ __('New :type', ['type' => \Modules\Invoicing\Models\Document::TYPES[$type]]) }}
            </a>
        </p>
    @endif

    @if (! empty($gridSource))
        <div class="card" style="padding:10px">
            <div data-grid
                 data-src="{{ $gridSource }}"
                 data-columns='@json($gridColumns)'
                 data-row-href="{{ route($formBase . '.edit', ['document' => '__ID__']) }}"
                 data-per-page="100"
                 data-empty="{{ __('Nothing here yet.') }}"></div>
        </div>
    @else
    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ $partyLabel }}</th>
                <th>{{ __('Issued') }}</th>
                @if ($dueLabel)<th>{{ $dueLabel }}</th>@endif
                <th class="right-align">{{ $totalLabel }}</th>
                @if ($showsBalance)<th class="right-align">{{ __('Balance') }}</th>@endif
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
            @forelse ($documents as $d)
                <tr>
                    <td><a href="{{ route($formBase . '.edit', $d) }}">{{ $d->number }}</a></td>
                    <td class="small">{{ $d->customer?->display_name ?? '—' }}</td>
                    <td class="small dim">{{ $d->issue_date?->toDateString() }}</td>
                    @if ($dueLabel)
                        <td class="small dim">{{ $d->due_date?->toDateString() ?? '—' }}</td>
                    @endif
                    <td class="right-align">{{ $d->formattedTotal() }}</td>
                    @if ($showsBalance)
                        <td class="right-align">{{ $d->formattedBalance() }}</td>
                    @endif
                    <td>
                        <span class="badge {{ $d->isOverdue() ? 'critical' : ($d->status === 'paid' ? 'resolved' : 'moderate') }}">
                            {{ $d->statusLabel() }}
                        </span>
                    </td>
                    <td class="right-align">
                        @if ($mayDelete)
                            <form method="POST" action="{{ route($formBase . '.destroy', $d) }}" class="inline-form"
                                  data-confirm="{{ __('Delete :number and its payments?', ['number' => $d->number]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                {{-- Six fixed columns, plus the two that depend on the type. --}}
                <tr><td colspan="{{ 6 + ($dueLabel ? 1 : 0) + ($showsBalance ? 1 : 0) }}" class="dim">
                    {{ __('Nothing here yet.') }}
                </td></tr>
            @endforelse
        </table>
    </div>

    {{ $documents->links() }}
    @endif
@endsection
