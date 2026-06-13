<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estoque fica na variante — produtos sem variante
        // terão uma variante "Padrão" criada automaticamente
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('estoque')->default(0)->after('preco');
            $table->integer('estoque_minimo')->default(5)->after('estoque');
        });

        // Produtos simples (sem variante) também precisam de estoque
        // Resolvemos isso criando uma variante "Padrão" para eles
        // A coluna abaixo indica se a variante foi criada automaticamente
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('estoque_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['estoque', 'estoque_minimo', 'is_default']);
        });
    }
};
