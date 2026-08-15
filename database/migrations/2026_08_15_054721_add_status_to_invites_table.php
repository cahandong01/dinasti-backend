<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            // Maker-Checker (four-eyes principle): invite dari TENANT_ADMIN
            // WAJIB approved SUPER_ADMIN dulu sebelum bisa di-accept.
            // Invite dari SUPER_ADMIN langsung approved (dia otoritas tertinggi).
            $table->string('status')->default('pending_approval')->after('role');
            $table->uuid('approved_by')->nullable()->after('invited_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_by', 'approved_at']);
        });
    }
};