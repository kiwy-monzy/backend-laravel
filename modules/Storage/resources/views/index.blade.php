@extends('layouts.app')
@section('title', __('Storage'))

@section('content')
    <h1>{{ __('Storage') }}</h1>
    <p class="sub">
        {{ $organization?->name }} ·
        <code>uploads/{{ \Illuminate\Support\Str::limit($organization?->id, 8, '…') }}/</code>
    </p>

    <div class="grid c3">
        <div class="stat">
            <div class="n">{{ $bytes > 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024) . ' KB' }}</div>
            <div class="k">{{ __('Used by this organization') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ number_format($fileCount) }}</div>
            <div class="k">{{ __('Files') }}</div>
        </div>
        <div class="stat">
            <div class="n">{{ $collections->count() }}</div>
            <div class="k">{{ __('Collections') }}</div>
        </div>
    </div>

    <p class="dim small" style="margin-top:10px">
        {{ __('Backups copy this organization\'s directory only.') }}
    </p>

    <div class="grid c3" style="margin-top:16px">
        @foreach ($collections as $collection)
            <a class="card" href="{{ route('storage.collections.show', $collection->slug) }}" style="display:block">
                <strong>{{ $collection->name }}</strong>
                @if ($collection->is_system)
                    <span class="chip">{{ __('standard') }}</span>
                @endif
                <div class="dim small" style="margin-top:4px">{{ $collection->description }}</div>
                <div class="dim small" style="margin-top:8px">
                    {{ trans_choice(':count file|:count files', $collection->uploads_count, ['count' => $collection->uploads_count]) }}
                    · {{ __('writable by :role and above', ['role' => \App\Support\Access::roleLabel($collection->min_role)]) }}
                </div>
                @unless ($collection->selectable)
                    <div class="dim small">{{ __('hidden from the image picker') }}</div>
                @endunless
            </a>
        @endforeach
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('storage.collections.store') }}" class="card">
            @csrf
            <h2 style="margin-top:0">{{ __('New collection') }}</h2>
            <p class="dim small">
                {{ __('A folder with a permission on it.') }}
            </p>
            <div class="row">
                <label>
                    <span>{{ __('Name') }}</span>
                    <input type="text" name="name" required maxlength="90">
                </label>
                <label>
                    <span>{{ __('Description') }}</span>
                    <input type="text" name="description" maxlength="190">
                </label>
                <label>
                    <span>{{ __('Who may add files') }}</span>
                    <select name="min_role">
                        @foreach (\App\Support\Access::ROLES as $r)
                            <option value="{{ $r }}" @selected($r === 'employee')>
                                {{ \App\Support\Access::roleLabel($r) }} {{ __('and above') }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div><button class="btn" type="submit">{{ __('Create') }}</button></div>
            </div>
        </form>
    @endif
@endsection
