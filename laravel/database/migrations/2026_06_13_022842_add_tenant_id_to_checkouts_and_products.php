<?php
// database/migrations/xxxx_add_tenant_id_to_checkouts_and_products.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Checkouts ─────────────────────────────────────────────────────────
        if (!Schema::hasColumn('checkouts', 'tenant_id')) {
            Schema::table('checkouts', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->onDelete('cascade');
            });
        }

        // ── Categories ────────────────────────────────────────────────────────
        if (!Schema::hasColumn('categories', 'tenant_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->onDelete('cascade');
            });
        }

        // ── Products ──────────────────────────────────────────────────────────
        if (!Schema::hasColumn('products', 'tenant_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->onDelete('cascade');
            });
        }

        // ── Preenche registros existentes ─────────────────────────────────────
        $tenantId = \App\Models\Tenant::first()?->id;

        if ($tenantId) {
            DB::table('checkouts')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('categories')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('products')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
