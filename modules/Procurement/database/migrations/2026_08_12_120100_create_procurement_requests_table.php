<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement — Purchase requests, quotations and the approvals they need.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('reference');
            $table->string('requested_by')->nullable();
            $table->string('department')->nullable();
            $table->string('title');
            $table->string('status')->default('submitted');
            $table->string('priority')->default('normal');
            $table->bigInteger('estimated_minor')->default(0);
            $table->date('requested_on');
            $table->date('needed_by')->nullable();
            $table->text('justification')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_requests');
    }
};
