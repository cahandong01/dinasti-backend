<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom minimal buat legal review gate (D7) — bukan audit trail
     * lengkap (E35, akan dibangun terpisah nanti begitu kebutuhan
     * lintas-modul lebih jelas). Cukup buat tau state terakhir: siapa
     * yang approve/tolak, kapan.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignUuid('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};