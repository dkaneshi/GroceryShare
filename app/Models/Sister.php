<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sister extends Model
{
    protected $fillable = ['name', 'email'];

    public function shares(): HasMany
    {
        return $this->hasMany(GroceryShare::class);
    }

    public function outstandingTotal(): float
    {
        return $this->shares()->where('is_paid', false)->sum('amount');
    }
}
