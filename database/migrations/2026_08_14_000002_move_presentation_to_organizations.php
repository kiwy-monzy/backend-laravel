<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The template and theme move onto the organization profile, the same way the
 * `general` content did: one look for the tenant, chosen on the profile.
 *
 * Each organization inherits the look of its first active website, then the
 * per-website columns are nulled so the profile is the only tap that writes
 * them — a website whose organization has a look simply renders in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('template')->nullable()->after('general');
            $table->string('theme')->nullable()->after('template');
            $table->json('theme_overrides')->nullable()->after('theme');
        });

        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            $site = DB::table('websites')
                ->where('organization_id', $orgId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->first()
                ?? DB::table('websites')
                    ->where('organization_id', $orgId)
                    ->orderBy('created_at')
                    ->first();

            if (! $site) {
                continue;
            }

            DB::table('organizations')->where('id', $orgId)->update([
                'template' => $site->template ?: null,
                'theme' => $site->theme ?: null,
                'theme_overrides' => $site->theme_overrides,
            ]);
        }

        // Nullable so `null` means "the organization decides". The columns stay
        // for previews, which set the attributes directly on the loaded site.
        Schema::table('websites', function (Blueprint $table) {
            $table->string('template')->nullable()->change();
            $table->string('theme')->nullable()->change();
        });

        DB::table('websites')->update(['template' => null, 'theme' => null, 'theme_overrides' => null]);
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('template')->default('template1')->change();
            $table->string('theme')->default('fge')->change();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['template', 'theme', 'theme_overrides']);
        });
    }
};