@extends('layouts.app')
@section('title', __('Storage'))

@section('content')
    <h1>{{ __('Storage') }}</h1>
    <p class="sub">
        {{ trans_choice(':count file|:count files', $uploads->count(), ['count' => $uploads->count()]) }}
        · {{ number_format($used / 1048576, 1) }} MB
        · <code>storage/app/public/uploads</code>
        · <a href="{{ route('storage.index') }}">{{ __('Collections') }}</a>
    </p>

    @if ($inlineCount > 0)
        <div class="flash bad">
            {{ trans_choice(
                ':count file is still stored as base64 in the database. Run `php artisan fge:consolidate-assets` to write it to disk.|:count files are still stored as base64 in the database. Run `php artisan fge:consolidate-assets` to write them to disk.',
                $inlineCount,
                ['count' => $inlineCount],
            ) }}
        </div>
    @endif

    <form method="POST" action="{{ route('uploads.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="row">
            <label>
                <span>{{ __('Upload files') }}</span>
                <input type="file" name="files[]" multiple required>
            </label>
            <div><button class="btn" type="submit">{{ __('Upload') }}</button></div>
        </div>
    </form>

    @if ($uploads->isEmpty())
        <p class="dim">{{ __('Nothing stored yet.') }}</p>
    @else
        <div class="card table-wrap">
            <table>
                <tr>
                    <th style="width:56px">{{ __('Preview') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Size') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('URL') }}</th>
                    <th style="width:180px"></th>
                </tr>
                @foreach ($uploads as $upload)
                    <tr>
                        <td>
                            @if (str_starts_with((string) $upload->mime, 'image/'))
                                <img src="{{ $upload->url }}" alt="{{ $upload->filename }}" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:6px;display:block">
                            @else
                                <div style="width:44px;height:44px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6b7280">{{ pathinfo($upload->filename, PATHINFO_EXTENSION) ?: 'file' }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $upload->filename }}</div>
                            <div class="dim small">{{ $upload->created_at?->format('Y-m-d') ?? '' }}</div>
                        </td>
                        <td class="dim small">{{ number_format($upload->size / 1024) }} KB</td>
                        <td class="dim small" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $upload->mime }}</td>
                        <td class="dim small" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="{{ $upload->url }}" target="_blank" rel="noopener">{{ $upload->url }}</a></td>
                        <td class="right-align" style="white-space:nowrap">
                            <a class="btn small ghost" href="{{ $upload->url }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
                            @php
                                $coll = $upload->collection?->slug ?? 'website';
                                $orgId = $upload->organization_id;
                                // Try to route to module edit if collection belongs to current org
                                $canEdit = $upload->collection && auth()->user()?->organization_id === $orgId;
                            @endphp
                            @if ($canEdit)
                                <a class="btn small" href="{{ route('storage.files.edit', [$coll, $upload->id]) }}">{{ __('Edit') }}</a>
                            @endif
                            <form method="POST" action="{{ route('uploads.destroy', $upload) }}" class="inline-form"
                                  data-confirm="{{ __('Delete this file?') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endsection
