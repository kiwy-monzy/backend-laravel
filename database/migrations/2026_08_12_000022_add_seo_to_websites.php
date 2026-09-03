<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search and social metadata, per website.
 *
 * **Columns rather than another content section.** SEO is per *site*, not per
 * page of content, and it has to be readable without assembling the whole
 * `siteData()` payload — the `<head>` is rendered before anything else and a
 * JSON decode per request to find a meta description is work for nothing.
 *
 * `og_image` is a storage path like every other image, so the picker and the
 * per-organization backup treat it the same as the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            // `index,follow` by default; a staging site sets `noindex,nofollow`
            // and that one field is the difference between a charity's draft
            // ranking above their real site or not.
            $table->string('robots')->default('index,follow');
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('website');
            $table->string('twitter_card')->default('summary_large_image');
            $table->string('twitter_site')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
                'robots', 'og_image', 'og_type', 'twitter_card', 'twitter_site',
            ]);
        });
    }
};
