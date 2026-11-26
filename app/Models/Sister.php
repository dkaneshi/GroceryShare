<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sister extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone'];

    public function shares(): HasMany
    {
        return $this->hasMany(GroceryShare::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Everything she owes minus everything she has paid. Negative means she has credit.
     */
    public function outstandingTotal(): float
    {
        return round((float) $this->shares()->sum('amount') - (float) $this->payments()->sum('amount'), 2);
    }
}
