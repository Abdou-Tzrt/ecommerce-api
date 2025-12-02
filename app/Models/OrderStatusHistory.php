<?php

namespace App\Models;

use App\Enum\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    // fillable properties
    protected $fillable = [
        'order_id',
        'from_status', 
        'to_status',
        'user_id',
        'notes'
    ];

    protected $casts = [
        'from_status' => OrderStatus::class,
        'to_status' => OrderStatus::class,
    ];
    
    /**
     * Get the order associated with this status change
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who made this status change
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
