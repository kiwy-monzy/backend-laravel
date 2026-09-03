<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three tiers instead of two, and one new table.
 *
 * **`users.role` was doing two jobs at once.** It said both "may you edit the
 * public website" and "how much of this installation do you run", which is why
 * an FGE admin and the person who operates the platform were the same word.
 * They are now separate questions:
 *
 *   users.role            system_admin | owner | member
 *                         who you are to the *installation*
 *   organization_members  admin | manager | salesperson | employee
 *                         what you do inside *one organization*
 *
 * A system admin sees every user and every organization. An owner sees their
 * own organization's team and nothing else. A member sees neither.
 *
 * `organization_modules` is the third gate, above the two that already exist:
 * the system grants an organization the modules it is entitled to at all, and
 * only then can its owner hand them out to roles. Without it an owner could
 * grant themselves Accounting by ticking a box, which makes the plan
 * meaningless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_modules', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('module');
            $table->boolean('granted')->default(true);
            // Who turned it on — this is a system-admin decision and the audit
            // question "who gave them Accounting" has to have an answer.
            $table->string('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'module']);
        });

        // owner → owner (they run an organization)
        // admin → member (they administer a website, not the installation)
        // user  → member
        DB::table('users')->where('role', 'admin')->update(['role' => 'member']);
        DB::table('users')->where('role', 'user')->update(['role' => 'member']);

        // The bootstrap owner becomes the installation's first system admin:
        // somebody has to be able to reach the system pages, and on a fresh
        // install that is the only account there is.
        $bootstrap = env('BOOTSTRAP_OWNER_USERNAME', 'fge_owner');
        DB::table('users')->where('username', $bootstrap)->update(['role' => 'system_admin']);

        if (! DB::table('users')->where('role', 'system_admin')->exists()) {
            $first = DB::table('users')->orderBy('created_at')->first();
            if ($first) {
                DB::table('users')->where('id', $first->id)->update(['role' => 'system_admin']);
            }
        }

        // Every organization keeps the modules it already had, so nobody loses
        // access the moment this migration runs.
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            foreach (\App\Support\Modules::slugs() as $module) {
                DB::table('organization_modules')->insert([
                    'organization_id' => $organizationId,
                    'module' => $module,
                    'granted' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_modules');

        DB::table('users')->where('role', 'system_admin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'member')->update(['role' => 'admin']);
    }
};
