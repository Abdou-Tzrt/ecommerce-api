<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::apiResource('/products', ProductController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'permission:create products'])->group(function () {
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
});

Route::apiResource('/categories', CategoryController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'permission:create categories'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});

Route::get('/categories/{category}/products', [CategoryController::class, 'products']);


Route::post('/filter', [ProductController::class, 'filter']);

require_once __DIR__.'/auth.php';

