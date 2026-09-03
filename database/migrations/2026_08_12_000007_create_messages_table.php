<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('website_id')->default('526122f2-a101-44d5-bca0-9d6de7bf9af6');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->default('');
            $table->text('subject')->default('');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};