<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$roles = [
    'admin' => 'Admin',
    'custmor' => 'Custmor',
    'delivery' => 'Delivery',
];

foreach ($roles as $prefix => $type) {

    Route::prefix($prefix)->group(function () use ($type) {

        // Routes publiques
        Route::post('/register', [AuthController::class, 'register'])
            ->defaults('type', $type);

        Route::post('/login', [AuthController::class, 'login'])
            ->defaults('type', $type);

        // Routes protégées
        Route::middleware(['auth.sanctum', "user.type:$type"])->group(function() use ($type) {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::get('/token', [AuthController::class, 'getAccessToken']);
        });

    });

}