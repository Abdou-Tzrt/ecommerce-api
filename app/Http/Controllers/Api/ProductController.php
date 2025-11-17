<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::all();
        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Products retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'sku' => 'required|string|max:100|unique:products',
            'is_active' => 'boolean',
        ]);

        $data['user_id'] = $request->user()->id;

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        // validate the request for update or merge with existing data 

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:products,slug,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $product->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $product->fill($data);
        
        if(isset($data['name'])) {
            $product->slug = Str::slug($data['name'], '-');
        }

        $product->save();

        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product updated successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ], 200);
        
    }

    // undo delete product with testing if user has admin role
    public function restore(Request $request, Product $product)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to restore this product',
            ], 403);
        }
        $product->restore();
        return response()->json([
            'success' => true,
            'data' => $product,
            'message' => 'Product restored successfully',
        ], 200);
    }

    // permanent "forcing" delete
    public function permanentDelete(Request $request, Product $product)
    {
        if ($request->user()->hasRole('admin')) {
            $product->forceDelete();
            return response()->json([
                'success' => true,
                'message' => 'Product permanently deleted successfully',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }

    // index of admin products
    public function adminIndex(Request $request)
    {
        if ($request->user()->hasRole('admin')) {
            $products = Product::withTrashed()->get();
            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products
            ], 200);
        }
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }

    // filter products by name, description, price
    public function filter(Request $request)
    {
        $products = Product::query()
            ->when($request->price_min, fn($query) =>
                $query->where('price', '>=', $request->price_min))
            ->when($request->price_max, fn($query) =>
                $query->where('price', '<=', $request->price_max))
            ->when($request->q, fn($query, $q) =>
                $query->where(fn($subQuery) =>
                    $subQuery->where('name', 'like', "%{$q}%")
                             ->orWhere('description', 'like', "%{$q}%")
                )
            )->get();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products
        ], 200);
    }


}
