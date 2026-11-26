<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroceryWeek extends Model
{
    use HasFactory;

    protected $fillable = ['week_date', 'total_amount', 'share_amount', 'notes'];

    protected $casts = [
        'week_date' => 'date',
        'total_amount' => 'decimal:2',
        'share_amount' => 'decimal:2',
    ];

    public function shares(): HasMany
    {
        return $this->hasMany(GroceryShare::class);
    }
}
