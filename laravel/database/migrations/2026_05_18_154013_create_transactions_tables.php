<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained('checkouts')->onDelete('cascade');
            $table->enum('tipo', ['entrada', 'saida', 'suprimento']);
            $table->enum('metodo_pagamento', ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'outros']);
            $table->decimal('valor', 10, 2);
            $table->string('descricao')->nullable();
            $table->enum('origem', ['balcao', 'whatsapp'])->default('balcao');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
