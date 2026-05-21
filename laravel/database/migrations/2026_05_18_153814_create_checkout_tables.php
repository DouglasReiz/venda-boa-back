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
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->decimal('valor_abertura', 10, 2)->default(0);
            $table->decimal('valor_fechamento', 10, 2)->nullable();
            $table->enum('status', ['aberto', 'fechado'])->default('aberto');
            $table->timestamp('data_abertura')->useCurrent();
            $table->timestamp('data_fechamento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
