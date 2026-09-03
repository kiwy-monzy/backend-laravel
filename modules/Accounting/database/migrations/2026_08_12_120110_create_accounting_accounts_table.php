<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting — Chart of accounts and journal entries behind the ledger.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('code', 20);
            $table->string('name');
            $table->string('account_type')->default('asset');
            $table->string('parent_code', 20)->nullable();
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
