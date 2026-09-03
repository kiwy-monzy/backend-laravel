<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The organization profile becomes the source of truth for a website's
 * `general` section — identity, contact, social links and visibility — so the
 * public API serves the tenant's profile rather than a per-website content row.
 *
 * The existing `content_sections` row for `general` is copied into
 * `organizations.general` (preferring each site's default language) and kept
 * as a fallback for websites whose organization has no profile yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('general')->nullable()->after('logo_url');
        });

        foreach (DB::table('websites')->whereNotNull('organization_id')->get(['id', 'organization_id', 'default_language']) as $website) {
            $preferred = $website->default_language ?: 'en';

            $row = DB::table('content_sections')
                ->where('website_id', $website->id)
                ->where('section', 'general')
                ->where('locale', $preferred)
                ->orderByDesc('created_at')
                ->first()
                ?? DB::table('content_sections')
                    ->where('website_id', $website->id)
                    ->where('section', 'general')
                    ->orderByDesc('created_at')
                    ->first();

            if (! $row) {
                continue;
            }

            DB::table('organizations')
                ->where('id', $website->organization_id)
                ->update(['general' => $row->data]);
        }
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('general'));
    }
};
