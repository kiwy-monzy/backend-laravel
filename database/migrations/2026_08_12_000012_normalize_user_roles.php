<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles become a closed lowercase set: owner | admin | user.
 *
 * The legacy Rust server wrote them capitalised and free-form ("Owner",
 * "Admin", occasionally something else), which meant every check was a string
 * comparison that had to guess the casing. One vocabulary, compared in one
 * place (App\Models\User), is the whole point.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->get(['id', 'role'])->each(function ($row) {
            DB::table('users')->where('id', $row->id)->update([
                'role' => match (strtolower(trim((string) $row->role))) {
                    'owner', 'superadmin', 'super_admin' => 'owner',
                    'admin', 'administrator' => 'admin',
                    default => 'user',
                },
            ]);
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'owner')->update(['role' => 'Owner']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'Admin']);
    }
};
