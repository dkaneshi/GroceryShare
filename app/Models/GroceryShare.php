<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroceryShare extends Model
{
    protected $fillable = ['grocery_week_id', 'sister_id', 'amount', 'is_paid', 'paid_at'];

    protected $casts = [
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function groceryWeek(): BelongsTo
    {
        return $this->belongsTo(GroceryWeek::class);
    }

    public function sister(): BelongsTo
    {
        return $this->belongsTo(Sister::class);
    }
}
