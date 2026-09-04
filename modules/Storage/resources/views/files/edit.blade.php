@extends('layouts.app')
@section('title', __('Edit :name', ['name' => $file->filename]))

@section('content')
    <h1>{{ __('Edit file') }}</h1>
    <p class="sub">
        <a href="{{ route('storage.index') }}">{{ __('Storage') }}</a> ·
        <a href="{{ route('storage.collections.show', $collection->slug) }}">{{ $collection->name }}</a> ·
        <code>{{ $collection->path() }}</code>
    </p>

    <div class="card" style="display:flex;gap:16px;align-items:center">
        @if ($file->isImage())
            <img src="{{ $file->url }}" alt="{{ $file->filename }}" style="width:96px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">
        @else
            <div style="width:96px;height:96px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#6b7280">{{ pathinfo($file->filename, PATHINFO_EXTENSION) ?: 'file' }}</div>
        @endif
        <div>
            <div style="font-weight:600">{{ $file->filename }}</div>
            <div class="dim small">{{ $file->humanSize() }} · {{ $file->mime }}</div>
            <div class="dim small" style="word-break:break-all"><code>{{ $file->url }}</code></div>
            <div class="dim small">{{ $file->created_at?->toDatetimeString() ?? '' }}</div>
            <div style="margin-top:6px"><a class="btn small ghost" href="{{ $file->url }}" target="_blank" rel="noopener">{{ __('Open') }}</a></div>
        </div>
    </div>

    <form method="POST" action="{{ route('storage.files.update', [$collection->slug, $file->id]) }}" enctype="multipart/form-data" class="card">
        @csrf
        @method('PUT')

        <div class="row">
            <label>
                <span>{{ __('Filename') }}</span>
                <input type="text" name="filename" value="{{ old('filename', $file->filename) }}" required maxlength="120">
                <span class="dim small">{{ __('Renaming will rename the file on disk and update gallery/content references.') }}</span>
            </label>

            <label>
                <span>{{ __('Collection') }}</span>
                <select name="collection" required>
                    @foreach ($collections as $c)
                        <option value="{{ $c->slug }}" @selected($c->slug === $collection->slug)>{{ $c->name }} — {{ $c->path() }}</option>
                    @endforeach
                </select>
                <span class="dim small">{{ __('Moving will relocate the file on disk.') }}</span>
            </label>

            <label>
                <span>{{ __('Replace file (optional)') }}</span>
                <input type="file" name="replace">
                <span class="dim small">{{ __('Upload a new file to replace the current one. Keeps the filename above.') }}</span>
            </label>
        </div>

        <div style="display:flex;gap:8px;margin-top:12px">
            <button class="btn" type="submit">{{ __('Save') }}</button>
            <a class="btn ghost" href="{{ route('storage.collections.show', $collection->slug) }}">{{ __('Cancel') }}</a>
        </div>
    </form>

    @if ($canWrite)
        <form method="POST" action="{{ route('storage.files.destroy', [$collection->slug, $file->id]) }}" class="card"
              data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $file->filename]) }}">
            @csrf
            @method('DELETE')
            <h2 style="margin-top:0;color:#dc2626">{{ __('Danger zone') }}</h2>
            <p class="dim small">{{ __('Deleting will remove the file from disk if nothing else uses it. Gallery images must be removed from the gallery first.') }}</p>
            <button class="btn danger" type="submit">{{ __('Delete file') }}</button>
        </form>
    @endif
@endsection
