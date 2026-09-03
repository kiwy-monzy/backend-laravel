<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `organization_members.user_id` becomes optional.
 *
 * The companion to the previous migration: a seat held by a named person with
 * no login has nothing to point at. The unique (organization_id, user_id) pair
 * still holds — SQL treats NULLs as distinct, so several login-less seats can
 * coexist while a real user still cannot be seated twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse to strand the login-less seats this exists to allow.
        DB::table('organization_members')->whereNull('user_id')->delete();

        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('user_id')->nullable(false)->change();
        });
    }
};
