<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    // values
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getAllowedTransitions(): array
    {
        return match ($this) {
            // Pending orders can be paid or cancelled
            self::PENDING => [self::PAID, self::CANCELLED],

            // Paid orders can move to processing or be cancelled (refund scenario)
            self::PAID => [self::PROCESSING, self::CANCELLED],

            // Processing orders can be shipped or cancelled (inventory issues)
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],

            // Shipped orders can only be delivered (final happy path)
            self::SHIPPED => [self::DELIVERED],

            // Delivered and cancelled are final states - no transitions allowed
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(OrderStatus $targetStatus): bool
    {
        return in_array($targetStatus, $this->getAllowedTransitions());
    }

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending Payment',
            self::PAID => 'Payment Confirmed',
            self::PROCESSING => 'Being Prepared',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::PENDING => 'status-warning',
            self::PAID => 'status-info', 
            self::PROCESSING => 'status-primary',
            self::SHIPPED => 'status-success',
            self::DELIVERED => 'status-success',
            self::CANCELLED => 'status-danger',
        };
    }
}
