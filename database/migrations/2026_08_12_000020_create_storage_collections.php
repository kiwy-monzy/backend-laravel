<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage becomes per-organization, filed into named collections.
 *
 * **One flat `uploads/` directory was a tenancy bug waiting to happen.** Every
 * organization's files sat in the same folder, so "back up this organization"
 * was not a question the disk could answer, a quota could not be measured, and
 * the image picker offered one charity another's photographs.
 *
 * The layout is now:
 *
 *     uploads/{organization_id}/{collection}/{file}
 *
 * so an organization's storage is one directory — which is what makes a
 * per-organization backup a `tar` of a path rather than a query, and its size a
 * `du` rather than a sum over rows.
 *
 * A collection is a folder with permissions on it: `website` for the public
 * site, `invoices` for proof-of-payment, plus any an owner creates ("Team
 * documents"). `min_role` is which team role may write to it, so an owner can
 * keep a collection managers can read but employees cannot fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_collections', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();

            // Which team role may *write* here. Reading follows module access.
            $table->string('min_role')->default('employee');
            // A system collection is created by the app and cannot be deleted:
            // removing `website` would orphan every image on the public site.
            $table->boolean('is_system')->default(false);
            $table->boolean('selectable')->default(true);

            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::table('uploads', function (Blueprint $table) {
            // Uploads were scoped to a *website*, which is the wrong tier: an
            // invoice attachment belongs to the organization and to no site at
            // all.
            $table->string('organization_id')->nullable()->index();
            $table->string('collection_id')->nullable()->index();
            // The path relative to the public disk, kept alongside the URL so
            // moving or deleting a file never has to parse the URL back.
            $table->string('path')->nullable();
        });

        // Seed the standard collections for every organization that exists.
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            foreach ([
                ['website', 'Website', 'Images used on the public site — hero, projects, team, gallery.', 'salesperson'],
                ['invoices', 'Invoices', 'Proof of payment and documents attached to invoices.', 'employee'],
                ['documents', 'Documents', 'General organization files.', 'employee'],
            ] as [$slug, $name, $description, $minRole]) {
                DB::table('storage_collections')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'organization_id' => $organizationId,
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'min_role' => $minRole,
                    'is_system' => true,
                    'selectable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Existing rows belong to the first organization's website collection —
        // every file on disk today is an image the FGE site is using.
        $first = DB::table('organizations')->orderBy('created_at')->value('id');
        if ($first) {
            $website = DB::table('storage_collections')
                ->where('organization_id', $first)->where('slug', 'website')->value('id');

            DB::table('uploads')->whereNull('collection_id')->update([
                'collection_id' => $website,
                'organization_id' => $first,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'collection_id', 'path']);
        });

        Schema::dropIfExists('storage_collections');
    }
};
