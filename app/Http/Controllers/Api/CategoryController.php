<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $catigories = Category::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Categories retrieved successfully',
            'data' => $catigories
        ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validate request
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $slug = $data['slug']
            ? Str::slug($data['slug'], '-')
            : Str::slug($data['name'], '-');
        
        if (Category::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(1, 99999);
        }

        $data['slug'] = $slug;
        $data['is_active'] = $data['is_active'] ?? true;

        $category = Category::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category
        ],201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->load(['parent', 'children']);

        return response()->json([
            'status' => 'success',
            'message' => 'Category retrieved successfully',
            'data' => $category
        ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        // validate request
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:categories,slug,'.$category->id,
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|nullable|boolean',
            'parent_id' => 'sometimes|nullable|exists:categories,id',
        ]);

        if (isset($data['slug'])) {
            $slug = Str::slug($data['slug'], '-');
        } elseif (isset($data['name'])) {
            $slug = Str::slug($data['name'], '-');
        } else {
            $slug = $category->slug;
        }

        if ($slug !== $category->slug && Category::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(1, 99999);
        }

        $data['slug'] = $slug;

        $category->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category
        ],200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
        foreach ($category->children as $child) {
            $child->parent_id = $category->parent_id;
            $child->save();
        }

        $category->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ],200);
    }

    public function products(Category $category)
    {
        $category->load('products');

        return response()->json([
            'status' => 'success',
            'message' => 'Products of Category retrieved successfully',
            'data' => $category->products
        ],200);
    }
}
