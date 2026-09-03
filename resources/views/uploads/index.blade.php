@extends('layouts.app')
@section('title', __('Storage'))

@section('content')
    <h1>{{ __('Storage') }}</h1>
    <p class="sub">
        {{ trans_choice(':count file|:count files', $uploads->count(), ['count' => $uploads->count()]) }}
        · {{ number_format($used / 1048576, 1) }} MB
        · <code>storage/app/public/uploads</code>
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
        <div class="media">
            @foreach ($uploads as $upload)
                <div class="tile">
                    @if (str_starts_with((string) $upload->mime, 'image/'))
                        <img src="{{ $upload->url }}" alt="{{ $upload->filename }}" loading="lazy">
                    @else
                        <div class="thumb">{{ pathinfo($upload->filename, PATHINFO_EXTENSION) ?: 'file' }}</div>
                    @endif

                    <div class="tile-body">
                        <div class="name">{{ $upload->filename }}</div>
                        <div class="dim small">{{ number_format($upload->size / 1024) }} KB</div>
                        <div class="actions">
                            <a class="btn small ghost" href="{{ $upload->url }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
                            <form method="POST" action="{{ route('uploads.destroy', $upload) }}"
                                  data-confirm="{{ __('Delete this file?') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
