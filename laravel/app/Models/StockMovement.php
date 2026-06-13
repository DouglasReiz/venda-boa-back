<?php
// app/Models/StockMovement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_variant_id', 'tenant_id', 'user_id',
        'tipo', 'quantidade', 'estoque_anterior', 'estoque_posterior',
        'motivo', 'transaction_id',
    ];

    protected $casts = [
        'quantidade'        => 'integer',
        'estoque_anterior'  => 'integer',
        'estoque_posterior' => 'integer',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}