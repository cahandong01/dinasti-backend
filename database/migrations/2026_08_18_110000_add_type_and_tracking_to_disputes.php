<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * API_CONTRACT.md Keputusan #2 & #3.
     *
     * - type: 'hak_jawab' | 'koreksi' (UU Pers Pasal 1 ayat 11 & 12).
     *   Batas waktu 2 bulan HANYA berlaku utk hak_jawab (lihat
     *   DisputeService).
     * - tracking_token: string acak OPAQUE, TIDAK bisa direverse jadi
     *   email — dipakai publik cek status tanpa expose PII di URL.
     * - is_self_reported: SELF-DECLARED oleh pelapor (checkbox di form),
     *   BUKAN diverifikasi identitas oleh backend — platform ini tidak
     *   punya sistem KYC.
     *
     * Sama seperti migration slug (2026_08_18_100000): backfill di
     * bawah WAJIB nonaktifkan RLS dulu, karena tabel `disputes` disimpan
     * TANPA carve-out publik (beda dari entities/relationships) — kalau
     * tidak, hanya baris yang tenant_id-nya cocok default context kosong
     * yang kebaca, sisanya kelewat backfill dan bikin ALTER SET NOT NULL
     * gagal sama seperti insiden slug.
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('type')->nullable()->after('disputable_id');
            $table->string('tracking_token')->nullable()->unique()->after('type');
            $table->boolean('is_self_reported')->default(false)->after('response_content');
        });

        DB::statement('ALTER TABLE disputes DISABLE ROW LEVEL SECURITY');

        $disputes = DB::table('disputes')->whereNull('type')->orWhereNull('tracking_token')->get(['id']);

        foreach ($disputes as $dispute) {
            DB::table('disputes')->where('id', $dispute->id)->update([
                'type' => 'hak_jawab', // default utk data lama, sebelum kolom ini ada
                'tracking_token' => Str::random(40),
            ]);
        }

        DB::statement('ALTER TABLE disputes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE disputes FORCE ROW LEVEL SECURITY');

        Schema::table('disputes', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
            $table->string('tracking_token')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['type', 'tracking_token', 'is_self_reported']);
        });
    }
};