@extends('layouts.app')
@section('title', $collection->name)

@section('content')
    <h1>{{ $collection->name }}</h1>
    <p class="sub">
        <a href="{{ route('storage.index') }}">{{ __('Storage') }}</a> ·
        <code>{{ $collection->path() }}</code> ·
        {{ $bytes > 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format(max(1, $bytes / 1024)) . ' KB' }}
    </p>

    @if ($canWrite)
        <form method="POST" action="{{ route('storage.collections.upload', $collection->slug) }}"
              enctype="multipart/form-data" class="card">
            @csrf
            <div class="row">
                <label>
                    <span>{{ __('Add files') }}</span>
                    <input type="file" name="files[]" multiple required>
                </label>
                <div><button class="btn" type="submit">{{ __('Upload') }}</button></div>
            </div>
        </form>
    @else
        <div class="flash bad">
            {{ __('View only — requires :role or above.', ['role' => \App\Support\Access::roleLabel($collection->min_role)]) }}
        </div>
    @endif

    <form method="GET" action="{{ route('storage.collections.show', $collection->slug) }}" class="card">
        <div class="row">
            <label>
                <span>{{ __('Search') }}</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Filename') }}">
            </label>
            <div><button class="btn ghost" type="submit">{{ __('Search') }}</button></div>
        </div>
    </form>

    @if ($files->isEmpty())
        <p class="dim">{{ __('Nothing here yet.') }}</p>
    @else
        <div class="card table-wrap">
            <table>
                <tr>
                    <th style="width:56px">{{ __('Preview') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Size') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Uploaded') }}</th>
                    <th style="width:210px"></th>
                </tr>
                @foreach ($files as $file)
                    <tr>
                        <td>
                            @if ($file->isImage())
                                <a href="{{ route('storage.files.edit', [$collection->slug, $file->id]) }}">
                                    <img src="{{ $file->url }}" alt="{{ $file->filename }}" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:6px;display:block">
                                </a>
                            @else
                                <div style="width:44px;height:44px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6b7280">{{ pathinfo($file->filename, PATHINFO_EXTENSION) ?: 'file' }}</div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('storage.files.edit', [$collection->slug, $file->id]) }}" style="font-weight:600">{{ $file->filename }}</a>
                            <div class="dim small" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $file->url }}</div>
                        </td>
                        <td class="dim small">{{ $file->humanSize() }}</td>
                        <td class="dim small">{{ $file->mime }}</td>
                        <td class="dim small">{{ $file->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="right-align" style="white-space:nowrap">
                            <a class="btn small ghost" href="{{ $file->url }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
                            <a class="btn small" href="{{ route('storage.files.edit', [$collection->slug, $file->id]) }}">{{ __('Edit') }}</a>
                            @if ($canDelete)
                                <form method="POST" action="{{ route('storage.files.destroy', [$collection->slug, $file->id]) }}" class="inline-form"
                                      data-confirm="{{ __('Delete :name?', ['name' => $file->filename]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

        {{ $files->links() }}
    @endif

    @if (! $collection->is_system && auth()->user()->isOwner())
        <form method="POST" action="{{ route('storage.collections.update', $collection->slug) }}" class="card">
            @csrf
            @method('PUT')
            <h2 style="margin-top:0">{{ __('Collection settings') }}</h2>
            <div class="row">
                <label>
                    <span>{{ __('Who may add files') }}</span>
                    <select name="min_role">
                        @foreach (\App\Support\Access::ROLES as $r)
                            <option value="{{ $r }}" @selected($collection->min_role === $r)>
                                {{ \App\Support\Access::roleLabel($r) }} {{ __('and above') }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('Description') }}</span>
                    <input type="text" name="description" value="{{ $collection->description }}" maxlength="190">
                </label>
                <label style="display:flex;gap:8px;align-items:center">
                    <input type="checkbox" name="selectable" value="1" style="width:auto" @checked($collection->selectable)>
                    <span style="margin:0">{{ __('Offer in the image picker') }}</span>
                </label>
                <div><button class="btn" type="submit">{{ __('Save') }}</button></div>
            </div>
        </form>
    @endif
@endsection
