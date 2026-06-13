<?php
// app/Models/ProductVariant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'nome',
        'preco',
        'estoque',
        'estoque_minimo',
        'is_default',
        'ativo',
    ];

    protected $casts = [
        'preco'          => 'float',
        'estoque'        => 'integer',
        'estoque_minimo' => 'integer',
        'is_default'     => 'boolean',
        'ativo'          => 'boolean',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── Helpers de estoque ────────────────────────────────────────────────────

    public function estaBaixoDoMinimo(): bool
    {
        return $this->estoque <= $this->estoque_minimo;
    }

    public function estaZerado(): bool
    {
        return $this->estoque <= 0;
    }

    /**
     * Baixa o estoque de forma segura dentro de uma transação DB.
     * Lança exceção se não houver estoque suficiente.
     */
    public function baixar(int $quantidade, int $transactionId = null, int $userId = null, int $tenantId = null): void
    {
        if ($this->estoque < $quantidade) {
            throw new \Exception(
                "Estoque insuficiente para \"{$this->product->nome} — {$this->nome}\". " .
                    "Disponível: {$this->estoque}, solicitado: {$quantidade}."
            );
        }

        $anterior = $this->estoque;
        $this->decrement('estoque', $quantidade);

        StockMovement::create([
            'product_variant_id' => $this->id,
            'tenant_id'          => $tenantId ?? $this->product?->tenant_id,
            'user_id'            => $userId,
            'tipo'               => 'saida',
            'quantidade'         => -$quantidade,
            'estoque_anterior'   => $anterior,
            'estoque_posterior'  => $this->estoque,
            'motivo'             => 'Venda PDV',
            'transaction_id'     => $transactionId,
        ]);
    }

    /**
     * Entrada de estoque (reposição ou ajuste manual).
     */
    public function repor(int $quantidade, string $motivo = 'Reposição manual', int $userId = null, int $tenantId = null): void
    {
        $anterior = $this->estoque;
        $this->increment('estoque', $quantidade);

        StockMovement::create([
            'product_variant_id' => $this->id,
            'tenant_id'          => $tenantId ?? $this->product?->tenant_id, // fallback
            'user_id'            => $userId,
            'tipo'               => 'entrada',
            'quantidade'         => $quantidade,
            'estoque_anterior'   => $anterior,
            'estoque_posterior'  => $this->estoque,
            'motivo'             => $motivo,
        ]);
    }
}
