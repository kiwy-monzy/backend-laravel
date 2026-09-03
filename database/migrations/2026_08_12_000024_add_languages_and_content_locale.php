<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-website languages, and a locale on each content section.
 *
 * **A translation is another row, not another field.** A content section is
 * already a JSON document; bolting a `translations` sub-key onto it would mean
 * every template that reads `hero.title` learning about locales. Giving the
 * section a `locale` and widening its key to `(website_id, section, locale)`
 * keeps every existing reader working — it just reads the row for the locale
 * in play, and the public site falls back to the default when a translation is
 * missing, so a half-translated site is never a broken one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('default_language', 8)->default('en');
            // The locales this site offers, as a JSON array. `null`/`[en]`
            // means a single-language site and the picker stays hidden.
            $table->json('languages')->nullable();
        });

        if (! Schema::hasColumn('content_sections', 'locale')) {
            Schema::table('content_sections', function (Blueprint $table) {
                $table->string('locale', 8)->default('en')->after('section');
            });

            // Existing rows are the default language. Set them to each site's
            // own default rather than a blanket 'en', so a Swahili-first site
            // keeps its content as the base.
            $defaults = DB::table('websites')->pluck('default_language', 'id');
            foreach ($defaults as $websiteId => $locale) {
                DB::table('content_sections')
                    ->where('website_id', $websiteId)
                    ->update(['locale' => $locale ?: 'en']);
            }

            // The unique key moves from (website_id, section) to include locale.
            // SQLite cannot alter a unique index in place, so it is dropped and
            // rebuilt; the `id` primary key is untouched.
            $this->rebuildContentUnique();
        }
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['default_language', 'languages']);
        });

        if (Schema::hasColumn('content_sections', 'locale')) {
            Schema::table('content_sections', fn (Blueprint $t) => $t->dropColumn('locale'));
        }
    }

    /**
     * Move the unique key onto (website_id, section, locale).
     *
     * **Checked, not caught.** This used to try each statement and swallow the
     * failure, which is harmless on SQLite and silently destructive on
     * PostgreSQL: one failed statement puts the surrounding transaction into an
     * aborted state, every later statement in it is refused, and the COMMIT
     * Laravel issues is downgraded to a ROLLBACK. The migration then *records
     * itself as run* — the repository insert happens after the transaction —
     * leaving a database that says it is migrated and is missing the columns.
     *
     * That is exactly what happened here: `websites.default_language` was added
     * a few lines above, thrown away by the rollback, and a later migration
     * selecting that column failed on a database that claimed to be up to date.
     */
    private function rebuildContentUnique(): void
    {
        foreach (['content_sections_website_id_section_unique'] as $name) {
            if (Schema::hasIndex('content_sections', $name)) {
                Schema::table('content_sections', fn (Blueprint $t) => $t->dropUnique($name));
            }
        }

        if (! Schema::hasIndex('content_sections', 'content_sections_site_section_locale')) {
            Schema::table('content_sections', function (Blueprint $table) {
                $table->unique(['website_id', 'section', 'locale'], 'content_sections_site_section_locale');
            });
        }
    }
};
