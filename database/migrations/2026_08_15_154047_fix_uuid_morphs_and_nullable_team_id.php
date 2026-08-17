<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BUG 1: personal_access_tokens.tokenable_id bawaan Sanctum itu
        // bigint (morphs()), padahal users.id kita UUID. Ganti ke uuidMorphs
        // (varian resmi Sanctum buat primary key non-bigint).
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['tokenable_id', 'tokenable_type']);
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuidMorphs('tokenable');
        });

        // BUG 2: gap desain lama spatie/laravel-permission (GitHub issue
        // #1893, #2888) — tenant_id di pivot table itu bagian primary key,
        // jadi TIDAK BISA null walau dokumentasi bilang role global boleh
        // null. Fix: keluarkan dari primary key, jadikan nullable.
        // Aman buat skema kita: role_id sudah unique per tenant (constraint
        // di tabel roles), jadi tenant_id di pivot ini redundan buat
        // disambiguasi, cuma informational.
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['tokenable_id', 'tokenable_type']);
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->morphs('tokenable');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->primary(['tenant_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->primary(['tenant_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }
};