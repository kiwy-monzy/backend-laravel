<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Access can now be granted a section at a time, not only a whole module.
 *
 * "Let the storekeeper see Stock but not Items" had no answer: the matrix was
 * per module, so the only choices were all of Inventory or none of it. A row
 * with a null `section` still governs the module as before — every existing row
 * keeps its meaning — and a row naming a section governs just that tab.
 *
 * The two combine deliberately: a section may be granted to a role that cannot
 * open the module generally, which is how someone gets exactly one tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_access', function (Blueprint $table) {
            $table->string('section')->nullable()->after('module');
        });

        // Replace the module-level unique key with one that includes the
        // section, or a module row and its section rows would collide.
        $this->reindex();
    }

    public function down(): void
    {
        DB::table('module_access')->whereNotNull('section')->delete();

        Schema::table('module_access', fn (Blueprint $t) => $t->dropColumn('section'));
    }

    /**
     * Widen the unique key to include the section.
     *
     * Checked rather than caught, for the reason set out in
     * 2026_08_12_000024: a swallowed SQL error inside a PostgreSQL transaction
     * aborts every statement that follows it and turns the commit into a
     * rollback, while the migration still records itself as having run.
     */
    private function reindex(): void
    {
        foreach (['module_access_organization_id_role_module_unique', 'module_access_org_role_module_unique'] as $name) {
            if (Schema::hasIndex('module_access', $name)) {
                Schema::table('module_access', fn (Blueprint $t) => $t->dropUnique($name));
            }
        }

        if (! Schema::hasIndex('module_access', 'module_access_scope_unique')) {
            Schema::table('module_access', function (Blueprint $table) {
                $table->unique(['organization_id', 'role', 'module', 'section'], 'module_access_scope_unique');
            });
        }
    }
};
