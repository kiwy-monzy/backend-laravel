<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three org roles, and a separate notion of what kind of employee someone is.
 *
 * **"Salesperson" was a role doing a job description's work.** It sat between
 * manager and employee purely to grant `edit`, which meant an organization
 * that wanted engineers or drivers with the same standing had nowhere to put
 * them — the ladder would have grown a rung per profession.
 *
 * Role now answers "how much authority" (admin / manager / employee) and
 * `employee_type` answers "what do they do" (salesperson, engineer, driver…).
 * The type is what shifts and contract activities are tracked against, and a
 * sales type additionally carries `edit` on customer-facing work — one
 * exception, stated once, instead of a whole rung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('employee_type')->nullable()->after('role');
        });

        // Anyone who was a salesperson becomes an employee of that type, so
        // nobody loses standing and the sales exception still applies to them.
        DB::table('organization_members')
            ->where('role', 'salesperson')
            ->update(['role' => 'employee', 'employee_type' => 'salesperson']);

        DB::table('module_access')->where('role', 'salesperson')->delete();
    }

    public function down(): void
    {
        DB::table('organization_members')
            ->where('employee_type', 'salesperson')
            ->update(['role' => 'salesperson']);

        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
    }
};
