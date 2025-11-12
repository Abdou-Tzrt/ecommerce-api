<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //register
    public function register(Request $request)
    {
        // Validate the request
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($data['password'])
        ]);

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return response
        return response()->json([
            '$data' => $user,
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    } 

    //login
    public function login(Request $request)
    {
        // Validate the request
        $data = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Find the user
        $user = User::where('email', $data['email'])->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return response
        return response()->json([
            'data' => $user,
            'message' => 'User logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    //logout
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'User logged out successfully'
        ], 200);
    }

    // get user profile
    public function profile(Request $request)
    {
        return response()->json([
            'data' => $request->user(),
            'message' => 'User profile retrieved successfully'
        ], 200);
    }

    //get access token
    public function getAccessToken(Request $request)
    {
        // get current access token
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }


}
