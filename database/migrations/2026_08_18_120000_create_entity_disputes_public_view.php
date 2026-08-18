<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * API_CONTRACT.md Keputusan #7 — riwayat dispute per-entity, PUBLIK,
     * tanpa data pribadi pelapor/reviewer.
     *
     * 2 lapis (riset: RLS Postgres itu row-level, BUKAN column-level —
     * kombinasi RLS + VIEW adalah rekomendasi standar buat kasus
     * "sebagian baris publik, sebagian kolom tetap privat"):
     *
     * 1. RLS carve-out row-level BARU, SELECT-only — izinkan baca baris
     *    resolved_accepted/resolved_rejected tanpa app.current_tenant.
     *    Baris pending TETAP terkunci ketat (policy lama tidak berubah).
     * 2. VIEW entity_disputes_public — HANYA berisi kolom non-PII.
     *    name/email/phone/tracking_token/resolved_by/is_self_reported
     *    SECARA STRUKTURAL tidak ada di view ini.
     *
     * CATATAN JUJUR (batasan, bukan celah tersembunyi): carve-out RLS di
     * langkah 1 berlaku di level TABEL asli, bukan cuma lewat view — jadi
     * kalau ada kode lain di masa depan yang query tabel `disputes`
     * mentah (bukan lewat view ini) untuk baris resolved tanpa tenant
     * context, PII teknisnya BISA ikut kebaca RLS-nya. Proteksi kolom
     * yang sesungguhnya ada di DISIPLIN KODE: HANYA DisputeService yang
     * boleh query publik, dan WAJIB lewat view ini, tidak pernah lewat
     * model Dispute langsung untuk endpoint publik.
     */
    public function up(): void
    {
        DB::statement("
            CREATE POLICY dispute_public_read_resolved ON disputes
            FOR SELECT
            USING (status IN ('resolved_accepted', 'resolved_rejected'))
        ");

        DB::statement("
            CREATE VIEW entity_disputes_public AS
            SELECT
                id,
                disputable_type,
                disputable_id,
                type,
                status,
                disputed_part,
                response_content,
                created_at,
                resolved_at,
                resolution_note
            FROM disputes
            WHERE status IN ('resolved_accepted', 'resolved_rejected')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS entity_disputes_public');
        DB::statement('DROP POLICY IF EXISTS dispute_public_read_resolved ON disputes');
    }
};