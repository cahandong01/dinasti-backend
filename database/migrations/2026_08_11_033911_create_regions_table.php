<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('level'); // country, province, city, dst
            $table->string('language_code')->default('id');
            $table->timestamps();
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('regions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};