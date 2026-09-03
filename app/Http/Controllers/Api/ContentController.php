<?php

namespace App\Http\Controllers\Api;

use App\Models\ContentSection;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends ApiController
{
    public const FGE_WEBSITE_ID = Website::FGE_WEBSITE_ID;

    public const SECTIONS = Website::SECTIONS;

    public function getWebsite(Request $request): JsonResponse
    {
        $website = Website::find(self::FGE_WEBSITE_ID);
        $data = $website ? $website->siteData() : [];

        if ($data === []) {
            return $this->json(['success' => true, 'website' => null]);
        }

        return $this->json([
            'success' => true,
            'website' => [
                'id' => self::FGE_WEBSITE_ID,
                'website_data' => $data,
            ],
        ]);
    }

    public function updateSection(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $section = $data['section'] ?? '';
        if (! in_array($section, self::SECTIONS, true)) {
            return $this->fail("unknown section: $section", 400);
        }

        // `general` — identity, contact, social links — is the organization's
        // profile now, so a client that writes it lands in the tenant rather
        // than a per-website content row.
        if ($section === 'general') {
            $website = Website::find(self::FGE_WEBSITE_ID);

            if ($website?->organization) {
                $organization = $website->organization;
                $organization->update([
                    'general' => array_replace_recursive($organization->general ?? [], $data['data'] ?? []),
                ]);
            }

            return $this->ok();
        }

        ContentSection::updateOrCreate(
            ['website_id' => self::FGE_WEBSITE_ID, 'section' => $section],
            ['data' => $data['data'] ?? null]
        );

        return $this->ok();
    }

    public function updateAll(Request $request): JsonResponse
    {
        $data = $this->body($request);

        if (array_key_exists('general', $data) && $data['general'] !== null) {
            $website = Website::find(self::FGE_WEBSITE_ID);

            if ($website?->organization) {
                $organization = $website->organization;
                $organization->update([
                    'general' => array_replace_recursive($organization->general ?? [], $data['general']),
                ]);
            }
        }

        foreach (self::SECTIONS as $section) {
            if ($section === 'general' || ! array_key_exists($section, $data) || $data[$section] === null) {
                continue;
            }

            ContentSection::updateOrCreate(
                ['website_id' => self::FGE_WEBSITE_ID, 'section' => $section],
                ['data' => $data[$section]]
            );
        }

        return $this->ok();
    }
}
