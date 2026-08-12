<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_region_access', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('access_level')->default('read_only'); // full, read_only
            $table->timestamps();

            $table->unique(['tenant_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_region_access');
    }
};