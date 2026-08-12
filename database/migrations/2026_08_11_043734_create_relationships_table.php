<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUuid('target_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUuid('evidence_id')->constrained('evidences');
            $table->string('type'); // misal: directorship, shareholding, family_affiliation
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('draft'); // draft, pending_review, published
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE relationships
            ADD CONSTRAINT relationships_no_overlap
            EXCLUDE USING gist (
                source_entity_id WITH =,
                target_entity_id WITH =,
                type WITH =,
                daterange(valid_from, valid_until, \'[]\') WITH &&
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};