<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings — Services, staff availability and appointments.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings_appointments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('service');
            $table->string('customer')->nullable();
            $table->string('staff')->nullable();
            $table->string('status')->default('booked');
            $table->dateTime('starts_at');
            $table->integer('duration_minutes')->default(60);
            $table->string('location')->nullable();
            $table->bigInteger('price_minor')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings_appointments');
    }
};
