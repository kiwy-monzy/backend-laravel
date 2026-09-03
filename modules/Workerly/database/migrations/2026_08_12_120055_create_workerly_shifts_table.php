<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workforce — Shifts and activities per employee - who worked on what, when, and against which contract.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workerly_shifts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('employee');
            $table->string('employee_type')->nullable();
            $table->string('contract_id')->nullable();
            $table->string('project')->nullable();
            $table->string('activity');
            $table->date('worked_on');
            $table->decimal('hours', 8,2)->default(0);
            $table->string('status')->default('logged');
            $table->bigInteger('rate_minor')->default(0);
            $table->boolean('billable')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workerly_shifts');
    }
};
