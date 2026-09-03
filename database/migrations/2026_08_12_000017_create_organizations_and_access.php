<?php

use App\Models\Website;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Organizations, the tier above websites.
 *
 * **A website is something an organization owns, not the top of the tree.**
 * FGE the charity runs fge.or.tz, but it also has customers, invoices and a
 * subscription — none of which belong to a *website*. Putting the organization
 * above means the invoicing modules have somewhere to hang that is not "the
 * public site", and an organization can grow a second site without any of its
 * business records moving.
 *
 * Three tables:
 *
 *   organizations        — the tenant, with its plan and trial window
 *   organization_members — a user's seat and role in an organization
 *   module_access        — which roles may enter which modules, per organization
 *
 * `module_access` is deliberately per-organization rather than global: FGE may
 * want its managers in Expenses while another org does not, and hard-coding
 * the default matrix would make that a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('owner_id')->nullable()->index();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->default('TZ');
            $table->string('currency', 8)->default('TZS');
            $table->string('logo_url')->nullable();

            // The org's own seat, not what it bills its customers for.
            $table->string('plan')->default('free_trial');
            $table->string('subscription_status')->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();

            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('user_id')->index();
            // admin | manager | salesperson | employee
            $table->string('role')->default('employee');
            $table->string('job_title')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('module_access', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            $table->string('role');
            $table->string('module');
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'role', 'module']);
        });

        Schema::table('websites', function (Blueprint $table) {
            $table->string('organization_id')->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_id')->nullable()->index();
        });

        // Everything that exists today belongs to FGE — it is the only
        // organization there has ever been, so the backfill is unambiguous.
        //
        // Only a backfill, though. On a fresh database there is nothing to
        // carry over, and creating FGE here would be a second place that
        // invents an organization; App\Support\Bootstrap is the only one.
        if (DB::table('users')->count() === 0) {
            return;
        }

        $orgId = (string) Str::uuid();
        $owner = DB::table('users')->where('role', 'owner')->first();

        DB::table('organizations')->insert([
            'id' => $orgId,
            'name' => 'FGE',
            'slug' => 'fge',
            'owner_id' => $owner->id ?? null,
            'email' => 'info@fge.or.tz',
            'phone' => '+255 762 060 160',
            'address' => 'Mkonze Dodoma – Tanzania',
            'country' => 'TZ',
            'currency' => 'TZS',
            'plan' => 'free_trial',
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('websites')->update(['organization_id' => $orgId]);
        DB::table('users')->update(['organization_id' => $orgId]);

        // Seat every existing user. The site's owner/admin roles map onto the
        // organization ladder: an owner administers, an admin manages.
        foreach (DB::table('users')->get(['id', 'role']) as $user) {
            DB::table('organization_members')->insert([
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'role' => match ($user->role) {
                    'owner' => 'admin',
                    'admin' => 'manager',
                    default => 'employee',
                },
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_access');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');

        Schema::table('websites', fn (Blueprint $t) => $t->dropColumn('organization_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('organization_id'));
    }
};
