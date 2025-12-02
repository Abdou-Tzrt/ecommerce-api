<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderManagementController extends Controller
{
    /**
     * Display a listing of the orders for admin management.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     */
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'items.product'])
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->from_date, fn($q, $from) =>
                $q->whereDate('created_at', '>=', $from)
            )
            ->when($request->to_date, fn($q, $to) =>
                $q->whereDate('created_at', '<=', $to)
            )
            ->latest()
            ->paginate(15);

        return response()->json([
            'orders' => $orders,
            'available_statuses' => OrderStatus::values(),
        ]);
    }

    /**
     * Display the specified order details for admin management.
     * 
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     * 
     */
    public function show(Order $order)
    {
        // Load all related data for admin view
        $order->load([
            'user', 
            'items.product', 
            'statusHistory.changedBy'
        ]);

        return response()->json([
            'order' => $order,
            'available_transitions' => $order->getAllowedTransitions(),
        ]);
    }

    /**
     * Update the status of the specified order.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     * 
     */
    public function updateStatus(Request $request, Order $order)
    {
        // Validate the new status
        $request->validate([
            'status' => 'required|string|in:' . implode(',', OrderStatus::values()),
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            // Convert string to enum
            $newStatus = OrderStatus::from($request->status);
            
            // Attempt the transition
            $order->transitionTo($newStatus, Auth::user(), $request->notes);

            // Reload order with fresh data
            $order->load(['statusHistory.changedBy']);

            return response()->json([
                'success' => true,
                'message' => "Order status updated to {$newStatus->getLabel()}",
                'order' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel the specified order.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     * 
     */
    public function cancel(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            // Check if order can be cancelled
            if (!$order->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order cannot be cancelled in its current status.',
                ], 400);
            }

            // Cancel the order
            $order->transitionTo(OrderStatus::CANCELLED, Auth::user(), "Cancelled: " . $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Order has been cancelled',
                'order' => $order->fresh(['statusHistory.changedBy']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
