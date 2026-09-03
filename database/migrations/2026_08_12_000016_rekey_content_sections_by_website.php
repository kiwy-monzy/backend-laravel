<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `section` alone was the primary key, which capped the whole installation at
 * one website's content: adding a second site's "general" section failed on a
 * unique constraint. The key is (website_id, section) — a section belongs to a
 * site, and the same eleven names recur on every one.
 *
 * SQLite cannot alter a primary key in place, so the table is rebuilt and the
 * rows copied across. Written to be resumable: it works out which of the three
 * steps have already happened, because a rebuild that dies between the rename
 * and the copy leaves the only copy of a charity's website content sitting in
 * a table the next run has to find rather than overwrite.
 */
return new class extends Migration
{
    private const OLD = 'content_sections_old';

    public function up(): void
    {
        if (! Schema::hasTable('content_sections') && ! Schema::hasTable(self::OLD)) {
            return;
        }

        // Step 1 — set the old table aside, unless a previous run already did.
        if (! Schema::hasTable(self::OLD)) {
            Schema::rename('content_sections', self::OLD);
        }

        // Indexes are database-wide objects in SQLite and a table rename does
        // not rename them, so the index migration 000010 created is still
        // called `content_sections_website_id_index` — and step 2 would
        // collide with it while trying to create its own.
        DB::statement('DROP INDEX IF EXISTS content_sections_website_id_index');

        // Step 2 — the correctly keyed table.
        if (! Schema::hasTable('content_sections')) {
            Schema::create('content_sections', function (Blueprint $table) {
                $table->id();
                $table->string('website_id')->index();
                $table->string('section');
                $table->json('data');
                $table->timestamps();

                $table->unique(['website_id', 'section']);
            });
        }

        // Step 3 — copy, skipping anything already carried over.
        foreach (DB::table(self::OLD)->get() as $row) {
            $websiteId = $row->website_id ?? \App\Models\Website::FGE_WEBSITE_ID;

            $exists = DB::table('content_sections')
                ->where('website_id', $websiteId)
                ->where('section', $row->section)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('content_sections')->insert([
                'website_id' => $websiteId,
                'section' => $row->section,
                'data' => $row->data,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop(self::OLD);
    }

    public function down(): void
    {
        // Irreversible by design: going back would mean choosing which site's
        // eleven sections survive and discarding every other site's content.
    }
};
