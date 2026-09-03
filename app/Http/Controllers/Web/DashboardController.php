<?php

namespace App\Http\Controllers\Web;

use App\Models\Donation;
use App\Models\GalleryImage;
use App\Models\Message;
use App\Models\Upload;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Website;

class DashboardController extends AdminController
{
    public function index()
    {
        $site = $this->site();

        return view('dashboard', [
            'site' => $site,
            // What is actually in the organization's modules. The dashboard used
            // to report only the website, so an organization with a hundred
            // thousand records saw four zeros and no sign of its own work.
            'moduleStats' => \App\Support\ModuleStats::for($this->me()),
            'counts' => [
                'websites' => $this->me()->isOwner() ? Website::count() : 1,
                'users' => $this->scoped(User::query())->count(),
                'gallery' => $this->scoped(GalleryImage::query())->count(),
                'uploads' => $this->scoped(Upload::query())->count(),
            ],
            'engagement' => [
                'donations' => $this->scoped(Donation::query())->count(),
                'raised' => (float) $this->scoped(Donation::query())->where('status', 'approved')->sum('amount'),
                'volunteers' => $this->scoped(Volunteer::query())->count(),
                'unread' => $this->scoped(Message::query())->where('is_read', false)->count(),
            ],
            'latestMessages' => $this->scoped(Message::query())
                ->orderByDesc('created_at')->limit(6)->get(),
            'latestDonations' => $this->scoped(Donation::query())
                ->orderByDesc('created_at')->limit(6)->get(),
            // Which of the eleven sections have actually been filled in. An
            // empty section renders as a gap on the public site, so this is
            // the one dashboard number that predicts a visitor complaint.
            'sectionStatus' => collect(Website::SECTIONS)->mapWithKeys(fn (string $s) => [
                $s => filled($site?->sectionData($s)),
            ])->all(),
        ]);
    }
}
