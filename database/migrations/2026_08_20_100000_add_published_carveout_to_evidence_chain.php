<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Carve-out publik buat rantai evidence (entity_attributes,
     * evidences, sources) — sebelumnya CUMA entities/relationships yang
     * dapet carve-out, jadi EntityDetailService (eager-load attributes.
     * evidence.source) crash di request publik: (a) NULLIF belum ada
     * (string kosong gagal cast uuid — insiden yang sama kayak slug),
     * (b) belum ada carve-out sama sekali (walau NULLIF ditambah, tetap
     * nggak akan nampilin evidence dari entity yang published).
     *
     * Desain (evidence-first, publik WAJIB bisa telusuri klaim ->
     * evidence -> source buat data yang sudah published):
     * - entity_attributes: publik kalau entity induknya published
     * - evidences: publik kalau dirujuk entity_attribute ATAU
     *   relationship yang published
     * - sources: publik kalau dirujuk evidence yang (via 2 aturan di
     *   atas) sudah keliatan publik
     *
     * Subquery ke entities/relationships di sini AMAN kena RLS carve-out
     * mereka sendiri (baris published tetap kebaca regardless tenant
     * context), tidak perlu penanganan khusus.
     */
    public function up(): void
    {
        DB::statement("
            ALTER POLICY tenant_isolation ON entity_attributes
            USING (
                tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid
                OR EXISTS (
                    SELECT 1 FROM entities e
                    WHERE e.id = entity_attributes.entity_id AND e.status = 'published'
                )
            )
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON evidences
            USING (
                tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid
                OR EXISTS (
                    SELECT 1 FROM entity_attributes ea
                    JOIN entities e ON e.id = ea.entity_id
                    WHERE ea.evidence_id = evidences.id AND e.status = 'published'
                )
                OR EXISTS (
                    SELECT 1 FROM relationships r
                    WHERE r.evidence_id = evidences.id AND r.status = 'published'
                )
            )
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON sources
            USING (
                tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid
                OR EXISTS (
                    SELECT 1 FROM evidences ev
                    WHERE ev.source_id = sources.id
                    AND (
                        EXISTS (
                            SELECT 1 FROM entity_attributes ea
                            JOIN entities e ON e.id = ea.entity_id
                            WHERE ea.evidence_id = ev.id AND e.status = 'published'
                        )
                        OR EXISTS (
                            SELECT 1 FROM relationships r
                            WHERE r.evidence_id = ev.id AND r.status = 'published'
                        )
                    )
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER POLICY tenant_isolation ON entity_attributes
            USING (tenant_id = (current_setting('app.current_tenant'::text, true))::uuid)
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON evidences
            USING (tenant_id = (current_setting('app.current_tenant'::text, true))::uuid)
        ");

        DB::statement("
            ALTER POLICY tenant_isolation ON sources
            USING (tenant_id = (current_setting('app.current_tenant'::text, true))::uuid)
        ");
    }
};