<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Hub — providers, the services they supply, the requests customers
 * raise and the bookings those become.
 *
 * Four tables rather than one, because the four things have different
 * lifetimes: a provider outlives every booking it ever takes, a service is a
 * catalogue entry that is edited in place, a request is a customer's question
 * and a booking is the answer. Folding them together is what turns "show me
 * every open request" into a query with three status columns in it.
 *
 * Money is stored in minor units as integers throughout: `decimal` in SQLite is
 * a float, and a float is the wrong type for money. Percentages are not money
 * and stay decimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicehub_providers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('address')->nullable();
            $table->string('zone')->nullable();

            // Onboarding is a state, not a boolean: a denied applicant and one
            // who has not applied yet need different answers from the list.
            $table->string('status')->default('pending')->index();
            $table->decimal('commission_percent', 5, 2)->default(15);
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('servicehub_services', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('provider_id')->nullable()->index();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('price_minor')->default(0);
            $table->integer('duration_minutes')->default(60);
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('servicehub_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('reference', 20)->nullable();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();

            $table->string('service_id')->nullable()->index();
            $table->string('category')->nullable();
            $table->text('description')->nullable();

            $table->dateTime('preferred_at')->nullable();
            $table->string('address')->nullable();
            $table->string('zone')->nullable();
            $table->bigInteger('budget_minor')->default(0);

            $table->string('status')->default('pending')->index();
            $table->string('provider_id')->nullable()->index();

            $table->timestamps();
        });

        Schema::create('servicehub_bookings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('reference', 20)->nullable();
            $table->string('request_id')->nullable()->index();
            $table->string('provider_id')->nullable()->index();
            $table->string('service_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer')->nullable();

            $table->dateTime('scheduled_at')->nullable();
            $table->integer('duration_minutes')->default(60);
            $table->string('address')->nullable();

            $table->string('status')->default('pending')->index();
            $table->string('payment_status')->default('unpaid')->index();
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('commission_minor')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicehub_bookings');
        Schema::dropIfExists('servicehub_requests');
        Schema::dropIfExists('servicehub_services');
        Schema::dropIfExists('servicehub_providers');
    }
};
