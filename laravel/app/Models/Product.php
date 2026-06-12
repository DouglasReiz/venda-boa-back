<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'nome', 'preco', 'descricao', 'tem_variantes', 'ativo'];

    protected $casts = [
        'preco'         => 'float',
        'tem_variantes' => 'boolean',
        'ativo'         => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->where('ativo', true)->orderBy('nome');
    }
}
