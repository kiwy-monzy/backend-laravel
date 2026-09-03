<?php

namespace Modules\Storage\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use App\Models\GalleryImage;
use App\Models\StorageCollection;
use App\Models\Upload;
use App\Services\MediaLibrary;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The organization's files, filed into collections.
 *
 * Everything an organization uploads lives under one directory — which is what
 * makes "back this organization up" a copy of a path and "how much are they
 * using" a walk of it, rather than either being a query nobody can quite trust.
 *
 * Write permission is per collection: an owner can keep a `website` collection
 * only managers may fill while leaving `documents` open to everyone.
 */
class StorageController extends ModuleController
{
    protected string $module = 'storage';

    public function __construct(private MediaLibrary $media)
    {
    }

    public function index()
    {
        $organization = $this->organization();

        $collections = StorageCollection::where('organization_id', $this->organizationId())
            ->withCount('uploads')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('storage::index', [
            'organization' => $organization,
            'collections' => $collections,
            'bytes' => StorageCollection::organizationBytes((string) $this->organizationId()),
            'fileCount' => Upload::where('organization_id', $this->organizationId())->count(),
            'role' => $this->role(),
            'canManage' => $this->may('manage_users') || $this->me()->isOwner(),
        ]);
    }

    public function show(Request $request, string $collection)
    {
        $found = $this->findCollection($collection);

        return view('storage::collections.show', [
            'organization' => $this->organization(),
            'collection' => $found,
            'files' => $found->uploads()
                ->when($request->query('q'), fn ($q, $term) => $q->where('filename', 'like', "%$term%"))
                ->orderByDesc('created_at')
                ->paginate(60)
                ->withQueryString(),
            'q' => $request->query('q'),
            'bytes' => $found->bytes(),
            'canWrite' => $found->writableBy($this->role()),
            'canDelete' => $this->may('delete') && $found->writableBy($this->role()),
        ]);
    }

    public function store(Request $request, string $collection): RedirectResponse
    {
        $found = $this->findCollection($collection);
        $this->assertWritable($found);

        $data = $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        foreach ($data['files'] as $file) {
            $stored = $this->media->storeUpload($file, $this->organizationId(), $found->slug);
            $this->media->register($stored, $this->organizationId(), $found);
        }

        return back()->with('status', trans_choice(
            ':count file uploaded.|:count files uploaded.',
            count($data['files']),
            ['count' => count($data['files'])],
        ));
    }

    public function destroy(string $collection, string $upload): RedirectResponse
    {
        $found = $this->findCollection($collection);
        $this->assertWritable($found);
        $this->authorizeAction('delete');

        $file = $found->uploads()->find($upload);
        if (! $file) {
            throw new NotFoundHttpException('No such file.');
        }

        // A file the public gallery is showing would 404 on the live site the
        // moment it went; the gallery has to let go of it first.
        if (GalleryImage::where('url', $file->url)->exists()) {
            return back()->with('error', __('That file is still used by the gallery. Remove it there first.'));
        }

        $this->media->forget($file);

        return back()->with('status', __('File deleted.'));
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        $this->assertCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:90'],
            'description' => ['nullable', 'string', 'max:190'],
            'min_role' => ['required', 'in:' . implode(',', Access::ROLES)],
        ]);

        $slug = Str::slug($data['name']);

        if (StorageCollection::where('organization_id', $this->organizationId())->where('slug', $slug)->exists()) {
            return back()->with('error', __('A collection with that name already exists.'));
        }

        StorageCollection::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'min_role' => $data['min_role'],
            'is_system' => false,
            'selectable' => true,
        ]);

        return back()->with('status', __('Collection created.'));
    }

    public function updateCollection(Request $request, string $collection): RedirectResponse
    {
        $this->assertCanManage();
        $found = $this->findCollection($collection);

        $data = $request->validate([
            'min_role' => ['required', 'in:' . implode(',', Access::ROLES)],
            'description' => ['nullable', 'string', 'max:190'],
            'selectable' => ['nullable', 'boolean'],
        ]);

        $found->update([
            'min_role' => $data['min_role'],
            'description' => $data['description'] ?? null,
            'selectable' => $request->boolean('selectable'),
        ]);

        return back()->with('status', __('Collection updated.'));
    }

    public function destroyCollection(string $collection): RedirectResponse
    {
        $this->assertCanManage();
        $found = $this->findCollection($collection);

        if ($found->is_system) {
            return back()->with('error', __('The standard collections cannot be deleted.'));
        }

        if ($found->uploads()->exists()) {
            return back()->with('error', __('Empty the collection before deleting it.'));
        }

        $found->delete();

        return redirect()->route('storage.index')->with('status', __('Collection deleted.'));
    }

    /**
     * The picker's data source: images this member may actually choose from.
     *
     * Scoped to the organization and to collections marked selectable, so the
     * content editor can never offer another tenant's photographs — the thing
     * the flat `uploads/` directory made possible.
     */
    public function picker(Request $request): JsonResponse
    {
        $collections = StorageCollection::where('organization_id', $this->organizationId())
            ->where('selectable', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        $selected = $request->query('collection');

        $files = Upload::where('organization_id', $this->organizationId())
            ->where('mime', 'like', 'image/%')
            ->when($selected, fn ($q) => $q->whereHas(
                'collection',
                fn ($c) => $c->where('slug', $selected),
            ))
            ->when(
                ! $selected,
                fn ($q) => $q->whereIn('collection_id', $collections->pluck('id')),
            )
            ->when($request->query('q'), fn ($q, $term) => $q->where('filename', 'like', "%$term%"))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'collections' => $collections->map(fn (StorageCollection $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'writable' => $c->writableBy($this->role()),
            ])->values(),
            'files' => $files->map(fn (Upload $u) => [
                'url' => $u->url,
                'filename' => $u->filename,
                'size' => $u->humanSize(),
                'collection' => $u->collection?->slug,
            ])->values(),
        ]);
    }

    private function findCollection(string $slug): StorageCollection
    {
        $collection = StorageCollection::where('organization_id', $this->organizationId())
            ->where('slug', $slug)
            ->first();

        if (! $collection) {
            throw new NotFoundHttpException('No such collection.');
        }

        return $collection;
    }

    private function assertWritable(StorageCollection $collection): void
    {
        if (! $collection->writableBy($this->role())) {
            throw new AccessDeniedHttpException(sprintf(
                '%s cannot add files to %s — it is limited to %s and above.',
                Access::roleLabel($this->role()),
                $collection->name,
                Access::roleLabel($collection->min_role),
            ));
        }
    }

    private function assertCanManage(): void
    {
        if (! $this->me()->isOwner() && ! Access::can($this->role(), 'manage_users')) {
            throw new AccessDeniedHttpException('Only an organization administrator manages collections.');
        }
    }
}
