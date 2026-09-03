<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\GalleryImage;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The gallery.
 *
 * Every row's `url` is a `/storage/uploads/…` path — an upload here writes the
 * file to the public disk first and stores the path, so the gallery is always
 * served by the webserver from Laravel storage rather than inlined into JSON.
 */
class GalleryController extends AdminController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    public function index()
    {
        return view('website::gallery.index', [
            'site' => $this->site(),
            'images' => $this->scoped(GalleryImage::query())->orderBy('created_at')->get(),
            'gridColumns' => $this->grid()->spec(),
        ]);
    }

    /** The gallery as JSON, for the data grid. */
    public function data(Request $request)
    {
        return $this->grid()->json($request);
    }

    /**
     * The gallery as a table.
     *
     * A wall of thumbnails shows the pictures and nothing else — not the
     * caption that will appear beneath them, not whether one is hidden from the
     * site. The grid draws the picture *in the cell* and puts the rest of the
     * record beside it, which is what makes a gallery of two hundred images
     * reviewable rather than merely visible.
     */
    private function grid(): \App\Support\GridSource
    {
        return \App\Support\GridSource::make(
            $this->scoped(GalleryImage::query())->orderBy('created_at'),
            [
                'url' => [
                    'title' => __('Image'), 'type' => 'image', 'width' => 110,
                    // The grid's image cell takes a list, so one picture is a
                    // list of one; the same column can later hold several.
                    'value' => fn ($i) => $i->url ? [$i->url] : [],
                ],
                'caption' => ['title' => __('Caption'), 'width' => 320],
                'file' => [
                    'title' => __('File'), 'width' => 220, 'sort' => 'url',
                    'value' => fn ($i) => basename((string) $i->url),
                ],
                'disabled' => [
                    'title' => __('Shown on site'), 'type' => 'boolean', 'width' => 130,
                    // Stored as "disabled"; read as "shown", because that is
                    // the question anyone looking at a gallery is asking.
                    'value' => fn ($i) => ! $i->disabled,
                ],
                'created_at' => [
                    'title' => __('Added'), 'type' => 'date', 'width' => 120,
                    'value' => fn ($i) => $i->created_at?->toDateString(),
                ],
            ],
            ['caption', 'url'],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:12288'],
            'photo_url' => ['nullable', 'string', 'max:2000'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $site = $this->site();
        if (! $site) {
            return back()->with('error', __('No website is selected.'));
        }

        $urls = [];

        foreach ($data['images'] ?? [] as $file) {
            $stored = $this->media->storeUpload($file, $this->organizationId(), 'website');
            $urls[] = $stored['url'];
        }

        // A picture already in the organization's files can be put on the site
        // without uploading it again. It is checked against this organization's
        // own uploads first — the field is text, and text can be typed.
        if ($picked = trim((string) ($data['photo_url'] ?? ''))) {
            $owned = \App\Models\Upload::where('organization_id', $this->organizationId())
                ->where('url', $picked)
                ->exists();

            abort_unless($owned, 403, 'That file does not belong to this organization.');

            $urls[] = $picked;
        }

        if ($urls === []) {
            return back()->with('error', __('Choose files to upload, or pick one from storage.'));
        }

        foreach ($urls as $url) {
            GalleryImage::create([
                'id' => (string) Str::uuid(),
                'website_id' => $site->id,
                'url' => $url,
                'caption' => $data['caption'] ?? '',
                'disabled' => false,
            ]);
        }

        // Counted from what was actually added, not from the upload field —
        // a picture chosen from storage arrives with no file at all.
        return back()->with('status', trans_choice(
            ':count image added.|:count images added.',
            count($urls),
            ['count' => count($urls)],
        ));
    }

    public function update(Request $request, GalleryImage $image): RedirectResponse
    {
        $this->guard($image->website_id);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        $image->update([
            'caption' => $data['caption'] ?? '',
            'disabled' => (bool) ($data['disabled'] ?? false),
        ]);

        return back()->with('status', __('Image saved.'));
    }

    public function destroy(GalleryImage $image): RedirectResponse
    {
        $this->guard($image->website_id);

        $url = $image->url;
        $image->delete();

        // Only remove the file when nothing else points at it — the same photo
        // is often used in the gallery and in a project card.
        if (Str::startsWith($url, MediaLibrary::PREFIX)
            && ! GalleryImage::where('url', $url)->exists()
            && ! \App\Models\Upload::where('url', $url)->exists()) {
            Storage::disk('public')->delete(Str::after($url, '/storage/'));
        }

        return back()->with('status', __('Image deleted.'));
    }
}
