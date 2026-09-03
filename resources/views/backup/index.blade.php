@extends('layouts.app')
@section('title', __('Backup'))

@section('content')
    <h1>{{ __('Backup') }}</h1>
    <p class="sub">{{ $organization?->name }}</p>

    <div class="card">
        <h2 style="margin-top:0">{{ __('Download a full backup') }}</h2>
        <p>{{ __('One zip with every record this organization owns, plus a manifest. Optionally include the stored files.') }}</p>

        <div class="grid c2">
            <div class="stat">
                <div class="n">{{ $tableCount }}</div><div class="k">{{ __('Data tables') }}</div>
            </div>
            <div class="stat">
                <div class="n">{{ $storageBytes > 1048576 ? number_format($storageBytes / 1048576, 1) . ' MB' : number_format(max(1, $storageBytes / 1024)) . ' KB' }}</div>
                <div class="k">{{ __('Stored files') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('backup.download') }}" style="margin-top:14px">
            @csrf
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                <input type="checkbox" name="include_files" value="1" style="width:auto" checked>
                <span style="margin:0">{{ __('Include stored files (larger, slower)') }}</span>
            </label>
            <button class="btn" type="submit">{{ __('Download backup') }}</button>
        </form>

        <p class="dim small" style="margin-top:12px">
            {{ __('Everything an organization owns lives under one storage directory and every record carries its organization id, so a backup is a copy of one folder and a filtered dump of known tables — not "export everything and hope".') }}
        </p>
    </div>
@endsection
