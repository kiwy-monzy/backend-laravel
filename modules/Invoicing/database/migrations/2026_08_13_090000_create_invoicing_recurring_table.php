<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoice profiles.
 *
 * A profile is a standing instruction, not a document: it says who to bill,
 * how much, how often, and when the next one falls due. Issuing it produces an
 * ordinary invoice in `invoicing_documents`, so nothing downstream — payments,
 * ageing, the ledger — needs to know a recurrence existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_recurring', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organization_id')->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('title');
            $table->string('interval')->default('monthly');
            $table->date('next_run_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->string('status')->default('active');
            $table->unsignedInteger('issued_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_recurring');
    }
};
