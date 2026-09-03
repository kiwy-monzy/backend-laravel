@extends('layouts.app')
@section('title', __('Export'))

@section('content')
    <h1>{{ __('Export') }}</h1>
    <p class="sub">{{ __('Download your data as Excel or CSV.') }}</p>

    @unless ($canExport)
        <div class="flash bad">
            {{ __('Your role cannot export data. Ask an administrator if you need it.') }}
        </div>
    @endunless

    @if (empty($sources))
        <div class="card"><p class="dim">{{ __('Nothing to export from the modules you can reach.') }}</p></div>
    @else
        <div class="grid c2">
            @foreach ($sources as $key => $spec)
                <div class="card">
                    <div class="body">
                        <strong>{{ $spec['label'] }}</strong>
                        <div class="dim small" style="margin-top:4px">
                            {{ __('From :module', ['module' => \App\Support\Modules::label($spec['module'])]) }} ·
                            {{ count($spec['headers']) }} {{ __('columns') }}
                        </div>
                        <div class="actions" style="margin-top:10px">
                            <a class="btn small" href="{{ route('export.run', $key) }}">{{ __('Excel (.xlsx)') }}</a>
                            <a class="btn small ghost" href="{{ route('export.run', ['source' => $key, 'format' => 'csv']) }}">{{ __('CSV') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="dim small" style="margin-top:14px">
        {{ __('Export is a permission of its own — a role without it can read a list on screen but not pull it out of the building.') }}
    </p>
@endsection
