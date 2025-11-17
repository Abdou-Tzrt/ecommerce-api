<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'sku',
        'is_active',
        'user_id',
    ];

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
