<?php

namespace App\Models;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',
        'shipping_name',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_zipcode',
        'shipping_country',
        'shipping_phone',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'order_number',
        'notes',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function transitionTo(OrderStatus $newStatus, ?User $changedBy = null, ?string $notes = null): bool
    {
        // check if transition is allowed
        if ($this->status === $newStatus) {
            return false;
        }

        if (!$this->status->canTransitionTo($newStatus)) {
            return false;
        }

        $oldStatus = $this->status; // store old status
        // update order status
        $this->update(['status' => $newStatus]);
        // log status change
        $this->statusHistories()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'user_id' => $changedBy?->id ?? Auth::id(), // use current user if not provided
            'notes' => $notes,
        ]);

        return true;
    }
    
    // get allowed transitions
    public function getAllowedTransitions(): array
    {
        return $this->status->getAllowedTransitions();
    }

    // get latest status changes
    public function getLatestStatusChange()
    {
        return $this->statusHistories()->first();
    }


    // generate unique order number
    public static function generateOrderNumber()
    {
        $date = date('ymd');
        do {
            $orderNumber = 'ORD-' . $date . strtoupper(substr(uniqid(), -6));
        } while (self::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    // check if order can be cancelled
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            OrderStatus::PENDING, 
            OrderStatus::PAID, 
            OrderStatus::PROCESSING
        ]);
    }

    public function canAcceptPayment(): bool
    {
        return $this->payment_status === PaymentStatus::PENDING || 
            $this->payment_status === PaymentStatus::FAILED;
    }

    // mark order as paid
    public function markIsPaid($transactionId)
    {
        $this->update([
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::COMPLETED,
            'transaction_id' => $transactionId,
            'paid_at' => now(),
        ]);
    }

    // mark as failed payment
    public function markPaymentAsFailed()
    {
        $this->update([
            'payment_status' => PaymentStatus::FAILED,
        ]);
    }

}
