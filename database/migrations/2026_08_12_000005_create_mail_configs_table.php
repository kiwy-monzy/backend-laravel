<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_configs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('email');
            $table->string('username');
            $table->text('password');
            $table->string('incoming_host');
            $table->integer('incoming_port');
            $table->string('incoming_protocol')->default('imap');
            $table->string('incoming_security')->default('ssl');
            $table->string('outgoing_host');
            $table->integer('outgoing_port');
            $table->string('outgoing_security')->default('ssl');
            $table->string('linked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_configs');
    }
};