@extends('layouts.app')
@section('title', __('Payments'))

@section('content')
    <h1>{{ __('Payments') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <p class="nav" style="margin-bottom:14px">
        <a href="{{ route('invoicing.payments.index') }}" @class(['on' => ! $method])>{{ __('All') }}</a>
        @foreach (\Modules\Invoicing\Models\Payment::METHODS as $key => $label)
            <a href="{{ route('invoicing.payments.index', ['method' => $key]) }}"
               @class(['on' => $method === $key])>{{ $label }}</a>
        @endforeach
    </p>

    <div class="grid c4" style="margin-bottom:12px">
        <div class="stat">
            <div class="n">{{ \Modules\Invoicing\Models\Money::format($total, $organization?->currency ?? 'TZS') }}</div>
            <div class="k">{{ $method ? __('Received by :method', ['method' => \Modules\Invoicing\Models\Payment::METHODS[$method]]) : __('Received in total') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ number_format($payments->total()) }}</div>
            <div class="k">{{ __('Payments') }}</div>
        </div>
    </div>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Document') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Method') }}</th>
                <th>{{ __('Reference') }}</th>
                <th class="right-align">{{ __('Amount') }}</th>
            </tr>
            @forelse ($payments as $p)
                <tr>
                    <td class="small dim">{{ $p->paid_on?->toDateString() }}</td>
                    <td>
                        @if ($p->document)
                            <a href="{{ route('invoicing.invoices.edit', $p->document) }}">{{ $p->document->number }}</a>
                        @else
                            <span class="dim">—</span>
                        @endif
                    </td>
                    <td class="small">{{ $p->document?->customer?->display_name ?? '—' }}</td>
                    <td class="small">{{ $p->methodLabel() }}</td>
                    <td class="small dim">{{ $p->reference ?: '—' }}</td>
                    <td class="right-align">{{ \Modules\Invoicing\Models\Money::format($p->amount_minor, $organization?->currency ?? 'TZS') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('No payments recorded yet.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $payments->links() }}
@endsection
