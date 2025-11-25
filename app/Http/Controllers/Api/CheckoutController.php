<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\TryCatch;

class CheckoutController extends Controller
{
    /**
     * Handle the checkout process.
     */
    public function checkout(Request $request)
    {
        // Validate the request data
        $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_zipcode' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'payment_method' => 'nullable|in:credit_card,paypal', // if null default to 'cod'
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty'], 400);
        }

        $subtotal = 0;
        $items = []; // order items array

        foreach ($cartItems as $item) {
            $product = $item->product;
            // check if product is active
            if (!$product->is_active) {
                return response()
                    ->json(['message' => "Product '{$product->name}' is no longer available"], 400);
            }
            // check product stock
            if ($product->stock < $item->quantity) {
                return response()
                    ->json(['message' => "not enogh stock for product '{$product->name}'"], 400);
            }
            $itemSubTotal = round($product->price * $item->quantity, 2);
            $subtotal += $itemSubTotal;

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $item->quantity,
                'price' => $product->price,
                'subtotal' => $itemSubTotal,
            ];
        }
        
        // tax and shipping cost
        $tax = round($subtotal * 0.15, 2); // assuming 15% tax
        $shippingCost = 7.00; // flat rate shipping cost
        $total = round($subtotal + $tax + $shippingCost, 2);

        DB::beginTransaction();
        
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'shipping_name' => $request->shipping_name,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_state' => $request->shipping_state,
                'shipping_zipcode' => $request->shipping_zipcode,
                'shipping_country' => $request->shipping_country,
                'shipping_phone' => $request->shipping_phone,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => $request->payment_method ?? 'cod',
                'payment_status' => 'unpaid',
                'order_number' => Order::generateOrderNumber(),
                'notes' => $request->notes,
            ]);
            
            $user->orders()->save($order);

            // save order items
            foreach ($items as $item) {
                $order->orderItems()->create($item);
                
                // decrement product stock
                $product = Product::find($item['product_id']);
                $product->decrement('stock', $item['quantity']);
            }

            // clear user's cart
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checkout successful',
                'order' => $order->load('orderItems'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
        }
    }

     // simulate index method to return a list of orders called orderhistory
     public function orderHistory(Request $request)
     {
         $user = $request->user();
         $orders = $user->orders()->with('orderItems')->get();
 
         return response()->json([
             'message' => 'Order history retrieved successfully',
             'orders' => $orders,
             'status' => true
         ]);
     }
 
    // simulate show method to return a single order by id called orderDetails
    public function orderDetails(Request $request, $id)
    {
        $user = $request->user();
        $order = $user->orders()->with('orderItems')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found', 'status' => false], 404);
        }

        return response()->json([
            'message' => 'Order details retrieved successfully',
            'order' => $order,
            'status' => true
        ]);
    }
}
