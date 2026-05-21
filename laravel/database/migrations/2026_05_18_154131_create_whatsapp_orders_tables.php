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
        Schema::create('whatsapp_orders_tables', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_telefone');
            $table->string('cliente_nome')->nullable();
            $table->enum('status', ['menu', 'aguardando_pix', 'preparo', 'entrega', 'finalizado'])->default('menu');
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('comprovante_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_orders_tables');
    }
};
