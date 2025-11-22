<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();
        //get total price
        $total = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Cart items retrieved successfully',
            'cart' => $cartItems,
            'total' => $total,
        ]);
    }

    /**
     * Add new item to the cart.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem = Cart::firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $request->product_id
                ],['quantity' => 0 // quantity initial to 0
            ]);
        
        // incremente quantity by requested quantity
        $cartItem->increment('quantity', $request->quantity);
        
        // message dynamique
        $message = $cartItem->wasRecentlyCreated
            ? 'Product added to cart successfully'
            : 'Quantity updated successfully';
        
        $status = $cartItem->wasRecentlyCreated ? 201 : 200;
        
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'cart' => $cartItem,
        ], $status);
        

    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Cart item updated successfully',
            'cart' => $cart,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cart $cart)
    {
        $cart->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Cart item removed successfully',
        ]);
    }

    // clear cart
    public function clear(Request $request)
    {
        $user = $request->user();
        Cart::where('user_id', $user->id)->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Cart cleared successfully',
        ]);
    }
}
