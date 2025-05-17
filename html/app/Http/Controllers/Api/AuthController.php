<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized - Failed'], 401);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            auth('api')->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to logout, token invalid'], 500);
        }
    }

    /**
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        return $user
            ? response()->json($user)
            : response()->json(['error' => 'User not authenticated'], 401);
    }
}
