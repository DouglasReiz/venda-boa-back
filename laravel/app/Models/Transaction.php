<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = ['checkout_id', 'tipo', 'metodo_pagamento', 'valor', 'descricao', 'origem'];

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }
}
