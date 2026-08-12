<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // Misal: "LPSE Provinsi Banten", "Putusan Pengadilan No. XXX"
            $table->string('type'); // government_portal, court_ruling, news_article, dokumen_resmi, dst
            $table->string('url')->nullable();
            $table->string('reliability')->default('unverified'); // verified, unverified, disputed
            $table->date('published_at')->nullable(); // kapan sumber ini terbit/dikeluarkan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};