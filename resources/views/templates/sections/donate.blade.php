@php
    $donate = $data['donate'] ?? [];
    // The legacy admin stored payment channels under several names depending on
    // when the row was written; accepting all of them avoids a data migration
    // whose only purpose is to rename a key.
    $methods = $donate['methods'] ?? $donate['payment_methods'] ?? $donate['accounts'] ?? [];
@endphp

@if (($data['general']['visibility']['donate'] ?? true))
    <section class="section" id="donate">
        <div class="wrap">
            <div class="section-head" data-n="07">
                <h2>{{ $donate['title'] ?? __('Donate') }}</h2>
                <p>{{ $donate['description'] ?? __('Your support funds the programmes on this site.') }}</p>
            </div>

            @if (filled($methods))
                <div class="grid c3">
                    @foreach ($methods as $method)
                        <div class="card"><div class="body">
                            <h3>{{ $method['name'] ?? $method['title'] ?? __('Payment') }}</h3>
                            @foreach (['account_name' => __('Account name'), 'account_number' => __('Account number'), 'bank' => __('Bank'), 'branch' => __('Branch'), 'number' => __('Number'), 'instructions' => __('How to pay')] as $key => $label)
                                @if (! empty($method[$key]))
                                    <p class="meta"><strong>{{ $label }}:</strong> {{ $method[$key] }}</p>
                                @endif
                            @endforeach
                        </div></div>
                    @endforeach
                </div>
            @endif

            @if (! empty($donate['bank_details']) || ! empty($donate['instructions']))
                <div class="card" style="margin-top:22px"><div class="body">
                    <p>{{ $donate['bank_details'] ?? $donate['instructions'] }}</p>
                </div></div>
            @endif

            <p style="margin-top:24px">
                <a class="btn primary" href="{{ site_url($site, 'contact') }}">
                    {{ __('Talk to us about giving') }}
                </a>
            </p>
        </div>
    </section>
@endif
