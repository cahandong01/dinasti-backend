<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7, konsisten CONVENTIONS §1B
            $table->uuid('tenant_id');
            $table->string('email');
            $table->string('role'); // nama role Spatie, misal RESEARCHER
            $table->string('token')->unique(); // token undangan, dikirim manual (belum ada email infra)
            $table->uuid('invited_by'); // user_id yang bikin undangan
            $table->timestamp('accepted_at')->nullable(); // null = belum diklaim
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('invited_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};