<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Policy tenant_isolation yang sudah ada TETAP MENGUNCI SELECT/
     * UPDATE/DELETE per-tenant (LEGAL_REVIEWER cuma bisa lihat/proses
     * dispute tenant sendiri). Policy BARU ini KHUSUS buat INSERT —
     * dibutuhkan karena Dispute Submission itu endpoint PUBLIK
     * (app.current_tenant nggak pernah ke-set saat orang luar submit).
     *
     * AMAN karena: (1) nilai tenant_id yang ditulis SELALU diambil
     * dari server (DisputeService, dari entity/relationship yang
     * sudah tervalidasi published), BUKAN input mentah dari user;
     * (2) foreign key tenant_id -> tenants.id mencegah nilai
     * sembarangan. PostgreSQL menggabungkan multiple permissive
     * policy dengan OR, jadi policy lama tetap berlaku penuh buat
     * command selain INSERT.
     */
    public function up(): void
    {
        DB::statement('
            CREATE POLICY dispute_public_insert ON disputes
            FOR INSERT
            WITH CHECK (true)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS dispute_public_insert ON disputes');
    }
};