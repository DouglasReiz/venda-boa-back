<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['nome', 'slug', 'cnpj', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
