<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restrukturisasi kolom `disputes` biar sesuai struktur formal
     * Peraturan Dewan Pers No 9/2008 (Pedoman Hak Jawab):
     * - disputed_part: "bagian per bagian atau keseluruhan" (nullable
     *   = disengketakan secara keseluruhan)
     * - supporting_evidence: "wajib disertai data pendukung" (WAJIB)
     * - response_content: isi sanggahan/tanggapan itu sendiri (WAJIB)
     * Kolom `reason` lama DIHAPUS, digantikan 3 kolom ini (bukan
     * ditumpuk) — field lama itu ambigu, nggak jelas dia "sanggahan"
     * atau "data pendukung".
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('reason');
            $table->text('disputed_part')->nullable()->after('phone');
            $table->text('supporting_evidence')->after('disputed_part');
            $table->text('response_content')->after('supporting_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['disputed_part', 'supporting_evidence', 'response_content']);
            $table->text('reason')->after('phone');
        });
    }
};