<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah carve-out RLS: baris berstatus 'published' bisa dibaca
     * LINTAS TENANT tanpa app.current_tenant ter-set (buat akses
     * publik tanpa login — Dispute Submission, statistik homepage,
     * dst). Baris draft/pending_review/needs_revision TETAP terkunci
     * ketat per-tenant, TIDAK ADA perubahan di situ.
     *
     * NULLIF(..., '') WAJIB ADA: current_setting(..., true) balikin
     * NULL kalau variabel belum PERNAH di-set, TAPI balikin STRING
     * KOSONG kalau variabel di-RESET setelah pernah di-SET. String
     * kosong gagal di-cast ke uuid dan bikin query CRASH (bukan
     * cuma nolak akses) — ketahuan lewat test, bukan asumsi.
     */
    public function up(): void
    {
        DB::statement("
            ALTER POLICY tenant_isolation ON entities
            USING (
                tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid
                OR status = 'published'
            )
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON relationships
            USING (
                tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid
                OR status = 'published'
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER POLICY tenant_isolation ON entities
            USING (tenant_id = (current_setting('app.current_tenant'::text, true))::uuid)
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON relationships
            USING (tenant_id = (current_setting('app.current_tenant'::text, true))::uuid)
        ");
    }
};