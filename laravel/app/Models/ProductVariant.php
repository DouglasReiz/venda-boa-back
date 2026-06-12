<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'nome', 'preco', 'ativo'];

    protected $casts = [
        'preco' => 'float',
        'ativo' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
