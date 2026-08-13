<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aktifkan pg_trgm (D2) — dipakai buat fuzzy-match nama Indonesia
     * di fitur search, tanpa perlu Elasticsearch/Meilisearch di MVP.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};