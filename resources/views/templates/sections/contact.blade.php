@php
    $general = $data['general'] ?? [];
@endphp

<section class="section" id="contact">
    <div class="wrap">
        <div class="section-head" data-n="08">
            <h2>{{ __('Get in touch') }}</h2>
            <p>{{ __('Send us a message, or volunteer your time.') }}</p>
        </div>

        @if (session('sent'))
            <div class="note">{{ session('sent') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <strong>{{ __('Please check the form.') }}</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid c2">
            <form method="POST" action="{{ site_url($site, 'contact.send') }}" class="card">
                @csrf
                <div class="body">
                    <h3>{{ __('Send a message') }}</h3>

                    <div class="field">
                        <label for="c-name">{{ __('Your name') }}</label>
                        <input id="c-name" name="name" value="{{ old('name') }}" required maxlength="120">
                    </div>
                    <div class="field">
                        <label for="c-email">{{ __('Email') }}</label>
                        <input id="c-email" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="field">
                        <label for="c-phone">{{ __('Phone') }}</label>
                        <input id="c-phone" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div class="field">
                        <label for="c-subject">{{ __('Subject') }}</label>
                        <input id="c-subject" name="subject" value="{{ old('subject') }}">
                    </div>
                    <div class="field">
                        <label for="c-message">{{ __('Message') }}</label>
                        <textarea id="c-message" name="message" required maxlength="5000">{{ old('message') }}</textarea>
                    </div>

                    <button class="btn primary" type="submit">{{ __('Send message') }}</button>
                </div>
            </form>

            <form id="volunteer-form" class="card">
                <div class="body">
                    <h3>{{ __('Volunteer with us') }}</h3>

                    <div id="volunteer-success" class="note" style="display:none;"></div>
                    <div id="volunteer-error" class="errors" style="display:none;"></div>

                    <div class="field">
                        <label for="v-name">{{ __('Your name') }}</label>
                        <input id="v-name" name="name" required maxlength="120">
                    </div>
                    <div class="field">
                        <label for="v-email">{{ __('Email') }}</label>
                        <input id="v-email" type="email" name="email" required>
                    </div>
                    <div class="field">
                        <label for="v-phone">{{ __('Phone') }}</label>
                        <input id="v-phone" name="phone">
                    </div>
                    <div class="field">
                        <label for="v-skills">{{ __('Skills you can offer') }}</label>
                        <input id="v-skills" name="skills" maxlength="1000">
                    </div>
                    <div class="field">
                        <label for="v-availability">{{ __('When are you free?') }}</label>
                        <input id="v-availability" name="availability" maxlength="500">
                    </div>
                    <div class="field">
                        <label for="v-motivation">{{ __('Why do you want to help?') }}</label>
                        <textarea id="v-motivation" name="motivation" maxlength="2000"></textarea>
                    </div>
                    <input type="hidden" name="website_slug" value="{{ $site->slug }}">

                    <button class="btn primary" type="submit">{{ __('Sign me up') }}</button>
                </div>
            </form>

            <script>
                document.getElementById('volunteer-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const form = this;
                    const formData = new FormData(form);
                    const successDiv = document.getElementById('volunteer-success');
                    const errorDiv = document.getElementById('volunteer-error');
                    
                    // Hide previous messages
                    successDiv.style.display = 'none';
                    errorDiv.style.display = 'none';
                    
                    fetch('{{ url('/api/tickets/volunteer') }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            successDiv.textContent = data.message;
                            successDiv.style.display = 'block';
                            form.reset();
                        } else {
                            errorDiv.innerHTML = '<strong>' + (data.error || 'An error occurred') + '</strong>';
                            errorDiv.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        errorDiv.innerHTML = '<strong>Network error. Please try again.</strong>';
                        errorDiv.style.display = 'block';
                    });
                });
            </script>
        </div>

        @if (! empty($general['address']) || ! empty($general['contact_email']))
            <p class="meta" style="margin-top:22px">
                {{ $general['address'] ?? '' }}
                @if (! empty($general['contact_email'])) · {{ $general['contact_email'] }} @endif
                @if (! empty($general['contact_phone'])) · {{ $general['contact_phone'] }} @endif
            </p>
        @endif
    </div>
</section>
