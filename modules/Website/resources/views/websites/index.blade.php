@extends('layouts.app')
@section('title', __('Websites'))

@section('content')
    <h1>{{ __('Websites') }}</h1>
    <p class="sub">{{ __('Every site this installation serves.') }}</p>

    <p><a class="btn" href="{{ route('website.sites.create') }}">{{ __('Add website') }}</a></p>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Domain') }}</th>
                <th>{{ __('Template') }}</th>
                <th class="right-align">{{ __('Sections') }}</th>
                <th class="right-align">{{ __('Images') }}</th>
                <th class="right-align">{{ __('Users') }}</th>
                <th>{{ __('Live') }}</th>
                <th></th>
            </tr>
            @foreach ($websites as $w)
                <tr>
                    <td>
                        <a href="{{ route('website.sites.edit', $w) }}">{{ $w->name }}</a>
                        <div class="dim small">/s/{{ $w->slug }}</div>
                    </td>
                    <td class="small">{{ $w->domain ?: '—' }}</td>
                    <td class="small">{{ \App\Support\Templates::ALL[$w->templateKey()]['label'] }}</td>
                    <td class="right-align">{{ $w->sections_count }}</td>
                    <td class="right-align">{{ $w->gallery_images_count }}</td>
                    <td class="right-align">{{ $w->users_count }}</td>
                    <td><span class="badge {{ $w->is_active ? 'resolved' : 'offline' }}">{{ $w->is_active ? __('yes') : __('no') }}</span></td>
                    <td class="right-align">
                        <a class="btn small ghost" href="{{ site_url($w, 'home') }}" target="_blank" rel="noopener">{{ __('View') }}</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
