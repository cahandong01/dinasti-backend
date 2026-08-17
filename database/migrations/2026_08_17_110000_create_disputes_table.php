<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dispute Submission — jalur publik Hak Jawab/Hak Koreksi (UU Pers
     * No 40/1999). Polymorphic: bisa nempel ke entities ATAU
     * relationships (dua-duanya punya legal review gate D7).
     *
     * tenant_id disimpan LANGSUNG (bukan cuma via disputable_id) —
     * pola yang sama dengan entity_attributes/relationships (demi
     * performa RLS, lihat CONVENTIONS.md §1B). RLS dipasang biar
     * cuma tenant pemilik data yang disengketakan yang bisa lihat
     * dispute-nya — TIDAK ada carve-out publik di tabel ini (beda
     * dari entities/relationships), submitter TIDAK bisa lihat
     * daftar dispute orang lain.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuidMorphs('disputable'); // disputable_type + disputable_id

            $table->string('name');
            $table->string('email');
            $table->string('phone'); // No HP/WA yang bisa dihubungi

            $table->text('reason');

            $table->string('status')->default('pending'); // pending|resolved_accepted|resolved_rejected
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('resolved_by')->references('id')->on('users');
            
        });

        DB::statement('ALTER TABLE disputes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE disputes FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON disputes
            USING (tenant_id = NULLIF(current_setting('app.current_tenant'::text, true), '')::uuid)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};