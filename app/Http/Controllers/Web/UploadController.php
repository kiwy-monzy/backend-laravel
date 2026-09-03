<?php

namespace App\Http\Controllers\Web;

use App\Models\GalleryImage;
use App\Models\Upload;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The storage browser: everything on the public disk, with its usages. */
class UploadController extends AdminController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    public function index()
    {
        $uploads = $this->scoped(Upload::query())->orderByDesc('created_at')->get();

        return view('uploads.index', [
            'site' => $this->site(),
            'uploads' => $uploads,
            'used' => $uploads->sum('size'),
            // Anything still carrying base64 is a row the consolidation command
            // has not reached yet; surfacing the count is how anyone notices.
            'inlineCount' => $this->scoped(Upload::query())->where('url', 'like', 'data:%')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        foreach ($data['files'] as $file) {
            $stored = $this->media->storeUpload($file, $this->organizationId(), 'website');

            Upload::create([
                'id' => (string) Str::uuid(),
                'website_id' => $this->siteId(),
                'organization_id' => $this->organizationId(),
                'path' => $stored['path'],
                'filename' => $stored['filename'],
                'mime' => $stored['mime'],
                'size' => $stored['size'],
                'url' => $stored['url'],
                'created_at' => now(),
            ]);
        }

        return back()->with('status', trans_choice(
            ':count file uploaded.|:count files uploaded.',
            count($data['files']),
            ['count' => count($data['files'])],
        ));
    }

    public function destroy(Upload $upload): RedirectResponse
    {
        $this->guard($upload->website_id);

        if (GalleryImage::where('url', $upload->url)->exists()) {
            return back()->with('error', __('That file is still used by the gallery.'));
        }

        $url = $upload->url;
        $upload->delete();

        if (Str::startsWith($url, MediaLibrary::PREFIX) && ! Upload::where('url', $url)->exists()) {
            Storage::disk('public')->delete(Str::after($url, '/storage/'));
        }

        return back()->with('status', __('File deleted.'));
    }
}
