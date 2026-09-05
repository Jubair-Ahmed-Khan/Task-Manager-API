<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // register method
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);


        $user->assignRole('Employee');

        $user->load('roles');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ], 201);
    }


    // login method
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) 
        {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.'
                ],
            ]);
        }

        $user->load('roles');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ]);
    }

    // logout method
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    // current user method
    public function user(Request $request)
    {
        $user = $request->user();
        $user->load('roles');

        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
        ]);
    }
}