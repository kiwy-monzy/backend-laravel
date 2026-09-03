<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support — Support tickets from customers and staff, with priority, assignment and resolution.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('reference')->nullable();
            $table->string('subject');
            $table->string('requester')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('category')->default('question');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->string('assigned_to')->nullable();
            $table->date('due_on')->nullable();
            $table->date('resolved_on')->nullable();
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
