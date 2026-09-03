@extends('layouts.app')
@section('title', __('Financial statements'))

@php
    use Modules\Invoicing\Models\Money;
    $cur = $organization?->currency ?? 'TZS';
    $s = $statements;

    // A statement is read as a column of figures, so each block is the same
    // shape: rows, then the line that closes them.
    $block = function (string $title, $rows, int $total) use ($cur) {
        return compact('title', 'rows', 'total');
    };
@endphp

@section('content')
    <h1>{{ __('Financial statements') }}</h1>
    <p class="sub">{{ $organization?->name }} · {{ $from }} → {{ $to }}</p>

    <form method="GET" class="card" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <label><span>{{ __('From') }}</span><input type="date" name="from" value="{{ $from }}"></label>
        <label><span>{{ __('To') }}</span><input type="date" name="to" value="{{ $to }}"></label>
        <button class="btn" type="submit">{{ __('Apply') }}</button>
    </form>

    @if ($s['income']->isEmpty() && $s['expense']->isEmpty() && $s['asset']->isEmpty() && $s['liability']->isEmpty() && $s['equity']->isEmpty())
        <div class="card" style="margin-top:16px">
            <h2 style="margin-top:0">{{ __('No accounting data') }}</h2>
            <p>{{ __('Set up accounts and post entries first.') }}</p>
        </div>
    @else
        <div class="grid c4" style="margin-bottom:12px">
            <div class="stat"><div class="n">{{ Money::format($s['income_total'], $cur) }}</div><div class="k">{{ __('Income') }}</div></div>
            <div class="stat"><div class="n">{{ Money::format($s['expense_total'], $cur) }}</div><div class="k">{{ __('Expenses') }}</div></div>
            <div class="stat @if ($s['profit'] < 0) bad @endif">
                <div class="n">{{ Money::format($s['profit'], $cur) }}</div>
                <div class="k">{{ $s['profit'] < 0 ? __('Loss for the period') : __('Profit for the period') }}</div>
            </div>
            <div class="stat"><div class="n">{{ Money::format($s['asset_total'], $cur) }}</div><div class="k">{{ __('Assets') }}</div></div>
        </div>

        <div class="card book">
            <h2 style="margin-top:0">{{ __('Profit and loss') }}</h2>
            <table>
                @foreach ([$block(__('Income'), $s['income'], $s['income_total']), $block(__('Expenses'), $s['expense'], $s['expense_total'])] as $part)
                    <tr class="section"><td colspan="2">{{ $part['title'] }}</td><td class="num"></td></tr>

                    @forelse ($part['rows'] as $l)
                        <tr>
                            <td class="code" style="width:90px">{{ $l['account']->code }}</td>
                            <td>{{ $l['account']->name }}</td>
                            <td class="num" style="width:190px">{{ Money::format($l['balance'], $cur) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">{{ __('None') }}</td><td class="num muted">—</td></tr>
                    @endforelse

                    <tr class="subtotal">
                        <td></td>
                        <td>{{ __('Total :t', ['t' => \Illuminate\Support\Str::lower($part['title'])]) }}</td>
                        <td class="num">{{ Money::format($part['total'], $cur) }}</td>
                    </tr>
                @endforeach

                <tr class="total">
                    <td></td>
                    <td>{{ $s['profit'] < 0 ? __('Net loss') : __('Net profit') }}</td>
                    <td class="num @if ($s['profit'] < 0) neg @endif">{{ Money::format($s['profit'], $cur) }}</td>
                </tr>
            </table>
        </div>

        <div class="card book">
            <h2 style="margin-top:0">{{ __('Balance sheet') }}</h2>
            <table>
                @foreach ([$block(__('Assets'), $s['asset'], $s['asset_total']), $block(__('Liabilities'), $s['liability'], $s['liability_total']), $block(__('Equity'), $s['equity'], $s['equity_total'])] as $part)
                    <tr class="section"><td colspan="2">{{ $part['title'] }}</td><td class="num"></td></tr>

                    @forelse ($part['rows'] as $l)
                        <tr>
                            <td class="code" style="width:90px">{{ $l['account']->code }}</td>
                            <td>{{ $l['account']->name }}</td>
                            <td class="num" style="width:190px">{{ Money::format($l['balance'], $cur) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">{{ __('None') }}</td><td class="num muted">—</td></tr>
                    @endforelse

                    <tr class="subtotal">
                        <td></td>
                        <td>{{ __('Total :t', ['t' => \Illuminate\Support\Str::lower($part['title'])]) }}</td>
                        <td class="num">{{ Money::format($part['total'], $cur) }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal">
                    <td></td>
                    <td class="muted">{{ __('Profit for the period, carried to equity') }}</td>
                    <td class="num muted">{{ Money::format($s['profit'], $cur) }}</td>
                </tr>

                <tr class="total">
                    <td></td>
                    <td>{{ __('Liabilities + equity + profit') }}</td>
                    <td class="num">{{ Money::format($s['liability_total'] + $s['equity_total'] + $s['profit'], $cur) }}</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>{{ __('Assets') }}</td>
                    <td class="num">{{ Money::format($s['asset_total'], $cur) }}</td>
                </tr>
            </table>

            <p style="margin:12px 0 0">
                <span class="{{ $s['balances'] ? 'agrees' : 'disagrees' }}">
                    {{ $s['balances'] ? __('Balance sheet balances') : __('Balance sheet does not balance') }}
                </span>
            </p>
        </div>
    @endif
@endsection
