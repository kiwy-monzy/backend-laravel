<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments — The organization's departments and who heads them.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_departments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('head')->nullable();
            $table->string('cost_centre')->nullable();
            $table->bigInteger('budget_minor')->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_departments');
    }
};
