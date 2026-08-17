<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Beda dari `reviewed_at` (ke-overwrite tiap transisi status),
     * kolom ini WAJIB cuma keisi SEKALI — saat pertama kali status
     * jadi 'published'. Dibutuhkan buat validasi batas waktu 2 bulan
     * pengajuan Hak Jawab (Peraturan Dewan Pers No 9/2008), yang
     * WAJIB dihitung dari publikasi PERTAMA, bukan publikasi terakhir
     * kalau sempat direvisi berkali-kali.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->timestamp('first_published_at')->nullable()->after('reviewed_at');
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->timestamp('first_published_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('first_published_at');
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->dropColumn('first_published_at');
        });
    }
};