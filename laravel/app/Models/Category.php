<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['nome', 'cor', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class)->where('ativo', true)->orderBy('nome');
    }
}
