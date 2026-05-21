<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkout extends Model
{
    protected $fillable = ['valor_abertura', 'valor_fechamento', 'status', 'data_abertura', 'data_fechamento'];

    public function transacoes()
    {
        return $this->hasMany(Transaction::class);
    }
}
