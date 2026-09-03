<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use App\Models\Donation;
use App\Models\GalleryImage;
use App\Models\Message;
use App\Models\Volunteer;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The module opens on the organization's websites, not on one site's content.
 *
 * **An organization can have more than one site**, and the previous layout
 * quietly assumed a single one: Content, Gallery and Donations all operated on
 * "the current website" chosen from a titlebar dropdown, which is invisible
 * until you already know it is there. Choosing a site from a list first makes
 * the thing you are editing explicit.
 */
class OverviewController extends ModuleController
{
    protected string $module = 'website';

    public function index()
    {
        $sites = Website::where('organization_id', $this->organizationId())
            ->withCount(['sections', 'galleryImages'])
            ->orderBy('name')
            ->get();

        return view('website::index', [
            'organization' => $this->organization(),
            'sites' => $sites,
            'current' => $this->site(),
            'canAdd' => $this->me()->isOwner(),
            'stats' => $sites->mapWithKeys(fn (Website $s) => [$s->id => [
                'gallery' => $s->gallery_images_count,
                'sections' => $s->sections_count,
                'donations' => Donation::where('website_id', $s->id)->count(),
                'volunteers' => Volunteer::where('website_id', $s->id)->count(),
                'unread' => Message::where('website_id', $s->id)->where('is_read', false)->count(),
                'filled' => collect(Website::SECTIONS)
                    ->filter(fn (string $key) => filled($s->sectionData($key)))
                    ->count(),
            ]]),
        ]);
    }

    /**
     * One site: everything it holds, and the way in to each part.
     *
     * Selecting a site also makes it the active one for the rest of the
     * module, so Content and Gallery operate on what you just clicked rather
     * than on whatever the titlebar happened to be set to.
     */
    public function show(string $website)
    {
        $site = $this->findSite($website);

        session(['chrome.website_id' => $site->id]);

        return view('website::show', [
            'organization' => $this->organization(),
            'site' => $site,
            'counts' => [
                'gallery' => GalleryImage::where('website_id', $site->id)->count(),
                'donations' => Donation::where('website_id', $site->id)->count(),
                'volunteers' => Volunteer::where('website_id', $site->id)->count(),
                'unread' => Message::where('website_id', $site->id)->where('is_read', false)->count(),
            ],
            'sectionStatus' => collect(Website::SECTIONS)->mapWithKeys(fn (string $s) => [
                $s => filled($site->sectionData($s)),
            ])->all(),
            'gallery' => GalleryImage::where('website_id', $site->id)
                ->orderBy('created_at')->limit(12)->get(),
        ]);
    }

    /** The titlebar picker, kept because an owner works across sites. */
    public function switch(Request $request): RedirectResponse
    {
        $id = $request->input('website_id');

        $allowed = Website::where('organization_id', $this->organizationId())
            ->whereKey($id)
            ->exists();

        session(['chrome.website_id' => $allowed ? $id : null]);

        return back();
    }

    private function findSite(string $id): Website
    {
        $site = Website::where('organization_id', $this->organizationId())->find($id);

        if (! $site) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No such website.');
        }

        return $site;
    }
}
