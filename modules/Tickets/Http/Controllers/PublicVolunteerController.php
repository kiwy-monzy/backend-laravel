<?php

namespace Modules\Tickets\Http\Controllers;

use App\Models\Volunteer;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public volunteer form submissions from the website.
 * 
 * This handles volunteer applications from public website visitors
 * without requiring authentication.
 */
class PublicVolunteerController
{
    public function store(Request $request): JsonResponse
    {
        // Find the website by slug from the request
        $siteSlug = $request->input('website_slug');
        $website = Website::where('slug', $siteSlug)->where('is_active', true)->first();

        if (! $website) {
            return response()->json(['error' => 'Website not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'availability' => ['nullable', 'string', 'max:500'],
            'motivation' => ['nullable', 'string', 'max:2000'],
            'website_slug' => ['required', 'string'],
        ]);

        Volunteer::create($data + [
            'id' => (string) Str::uuid(),
            'website_id' => $website->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Thank you — we will be in touch.',
            'success' => true
        ]);
    }
}