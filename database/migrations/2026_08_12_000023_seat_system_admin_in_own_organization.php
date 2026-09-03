<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Superseded by App\Support\Bootstrap.
 *
 * This migration used to create the Knowlia organization, swap the `admin` and
 * `fge_owner` roles and grant Knowlia every module — all of which the
 * application also did at boot, differently. Two descriptions of the same
 * installation meant the result depended on which ran last.
 *
 * Bootstrap does all of it now, idempotently and on every boot, so an
 * installation that never saw this migration and one that ran it years ago
 * arrive at the same place. Kept as a no-op rather than deleted so the
 * migrations table on existing installations stays consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
