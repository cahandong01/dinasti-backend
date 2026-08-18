<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kolom slug — dipakai frontend buat URL SEO-friendly (/entitas/[slug]),
     * sesuai API_CONTRACT.md Keputusan #1. Slug UNIK GLOBAL, publik.
     *
     * PENTING: backfill di bawah ini adalah query DML biasa lewat koneksi
     * dinasti_app (non-superuser, D13) — TUNDUK RLS. Tanpa app.current_tenant
     * ter-set, backfill cuma akan "lihat" entity berstatus published (carve-out),
     * draft/pending_review/needs_revision akan TERLEWAT dan slug-nya tetap NULL
     * -- padahal ALTER SET NOT NULL nanti scan SEMUA baris fisik (bypass RLS),
     * jadi baris yang terlewat itu bikin migration gagal di langkah akhir.
     * Solusi: nonaktifkan RLS SEMENTARA khusus proses backfill ini saja.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        DB::statement('ALTER TABLE entities DISABLE ROW LEVEL SECURITY');

        $entities = DB::table('entities')->whereNull('slug')->get(['id', 'name']);

        foreach ($entities as $entity) {
            $baseSlug = Str::slug($entity->name);
            $slug = $baseSlug !== '' ? $baseSlug : 'entitas';
            $counter = 2;

            while (DB::table('entities')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            DB::table('entities')->where('id', $entity->id)->update(['slug' => $slug]);
        }

        DB::statement('ALTER TABLE entities ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entities FORCE ROW LEVEL SECURITY');

        Schema::table('entities', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};