<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Index GIN + pg_trgm (D2) di kolom entities.name — biar fuzzy-search
     * nama entity cepat, tidak sequential scan tiap query.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX entities_name_trgm_idx ON entities USING GIN (name gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS entities_name_trgm_idx');
    }
};