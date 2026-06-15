<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->onDelete('cascade');
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('tipo', ['entrada', 'saida', 'ajuste']);
            $table->integer('quantidade');           // positivo = entrada, negativo = saída
            $table->integer('estoque_anterior');
            $table->integer('estoque_posterior');
            $table->string('motivo')->nullable();    // "Venda PDV", "Ajuste manual", etc.
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null');             // vincula à venda quando é baixa automática
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
