<?php

use App\Models\Website;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last three tables that were still global.
 *
 * Donations, volunteers and uploads predate multi-site and carried no owner,
 * which would have shown every site's donors to every site's admin the moment
 * a second website existed. Existing rows all belong to FGE, so the backfill
 * is unambiguous.
 *
 * `remember_token` comes along because the session guard writes to it as soon
 * as anyone ticks "remember me", and a missing column there is a 500 at login.
 */
return new class extends Migration
{
    private const TABLES = ['donations', 'volunteers', 'uploads'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'website_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->string('website_id')->nullable()->index();
            });

            DB::table($table)->whereNull('website_id')
                ->update(['website_id' => Website::FGE_WEBSITE_ID]);
        }

        if (! Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $t) {
                $t->rememberToken();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'website_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('website_id'));
            }
        }

        if (Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('remember_token'));
        }
    }
};
