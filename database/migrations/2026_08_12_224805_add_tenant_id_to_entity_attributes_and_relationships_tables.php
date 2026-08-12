<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom tenant_id ke entity_attributes & relationships.
     * Alasan: RLS policy dengan subquery (lewat entity_id) tidak bisa
     * dioptimasi planner PostgreSQL — degradasi ke sequential scan.
     * tenant_id langsung di kolom bikin RLS pakai index, konsisten
     * sama pola di tabel lain (D10).
     */
    public function up(): void
    {
        Schema::table('entity_attributes', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('entity_id');
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        // Isi tenant_id dari entity yang direferensikan (data yang sudah ada).
        DB::statement('
            UPDATE entity_attributes
            SET tenant_id = entities.tenant_id
            FROM entities
            WHERE entity_attributes.entity_id = entities.id
        ');

        DB::statement('
            UPDATE relationships
            SET tenant_id = entities.tenant_id
            FROM entities
            WHERE relationships.source_entity_id = entities.id
        ');

        // Setelah terisi, kunci jadi NOT NULL + index (buat RLS & query cepat).
        Schema::table('entity_attributes', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->index('tenant_id');
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('entity_attributes', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};