<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization document numbering.
 *
 * References, codes and tags were typed by hand into every form, which meant
 * two people could invent the same one, everybody invented a different shape,
 * and a form asked its user for something the system already knew. The pattern
 * is now the organization's setting — owners and the system administrator
 * choose the prefix and width — and the number itself is allocated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->index();
            // What is being numbered: 'expense', 'asset', 'ticket'…
            $table->string('key');
            $table->string('prefix', 12)->default('');
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();

            // One counter per thing per organization; the uniqueness is what
            // makes the allocation safe to do with an atomic increment.
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
