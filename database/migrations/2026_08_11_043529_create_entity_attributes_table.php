<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('entity_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUuid('evidence_id')->constrained('evidences');
            $table->string('attribute_key'); // misal: position, shareholding_percent
            $table->text('attribute_value');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE entity_attributes
            ADD CONSTRAINT entity_attributes_no_overlap
            EXCLUDE USING gist (
                entity_id WITH =,
                attribute_key WITH =,
                daterange(valid_from, valid_until, \'[]\') WITH &&
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_attributes');
    }
};