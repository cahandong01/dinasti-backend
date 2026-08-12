<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->text('excerpt'); // potongan teks/kutipan spesifik dari source
            $table->string('locator')->nullable(); // halaman/paragraf/timestamp lokasi di source
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};