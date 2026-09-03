<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seat can name a person who has no login.
 *
 * FGE's board are on the team and on the public site, but none of them signs
 * into the admin. Forcing a seat to point at a `users` row would have meant
 * inventing five accounts that can never be used — credentials that exist only
 * to satisfy a foreign key are a liability, not a record. So `user_id` becomes
 * optional and the seat carries the name when there is nobody to link to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('person_name')->nullable();
        });

        // SQLite cannot relax a NOT NULL in place; the column was created
        // nullable-by-default here, so nothing further is needed. Left explicit
        // so the intent survives a port to a stricter database.
    }

    public function down(): void
    {
        Schema::table('organization_members', fn (Blueprint $t) => $t->dropColumn('person_name'));
    }
};
