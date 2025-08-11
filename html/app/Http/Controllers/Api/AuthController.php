<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Class AuthController
 * Handles authentication-related actions such as login, logout, user info retrieval, and token refresh.
 */
class AuthController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $credentials = [
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ];

        $token = auth('api')->attempt($credentials);
        if (!$token) {
            return response()->json(['error' => 'Unauthorized - Failed'], 401);
        }

        $user       = auth('api')->user();
        $expiresIn  = $this->getTtlSeconds();
        $roles      = $this->extractRoles($user);

        return response()->json([
            // New contract for the frontend (template1)
            'token'       => $token,
            'expires_in'  => $expiresIn,
            'user'        => $this->transformUser($user),
            'roles'       => $roles,

            // Backward compatibility with your existing response
            'access_token' => $token,
            'token_type'   => 'bearer',
        ], 200);
    }

    /**
     * Logout the user.
     * This endpoint is protected by auth:api middleware.
     * It will invalidate the current token and return a 204 No Content response.
     * If the user is already logged out, it will log the exception but not return it to the client.
     * This prevents leaking sensitive information and keeps the logout process idempotent.
     * If the user is already logged out, we can safely ignore this.
     * This is a common practice to avoid exposing internal errors.
     * The user will still receive a 204 No Content response.
     *
     * @return Response
     */
    public function logout(): Response
    {
        try {
            auth('api')->logout();
        } catch (Exception $e) {
            logger(
                'Logout exception at AuthController@logout',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth('api')->id(),
                ]
            );
        }

        return response()->noContent(); // 204
    }

    /**
     * Return the current authenticated user plus roles.
     * Route protected by auth:api middleware.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        return $user
            ? response()->json([
                'user'  => $this->transformUser($user),
                'roles' => $this->extractRoles($user),
            ])
            : response()->json([
                'error' => 'User not authenticated'
            ], 401);
    }

    /**
     * Refresh the JWT token.
     * This endpoint is protected by auth:api middleware.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            // Rotate token and invalidate the previous one
            $newToken = auth('api')->refresh();
        } catch (Throwable $e) {
            // If the token refresh fails, it might be due to an invalid token, log the error
            logger(
                'Token refresh failed at AuthController@refresh',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth('api')->id(),
                ]
            );
            return response()->json([
                'message' => 'Token refresh failed.',
            ], 401);
        }

        $expiresIn = $this->getTtlSeconds();

        return response()->json([
            // New contract (used by frontend templates)
            'token'       => $newToken,
            'expires_in'  => $expiresIn,
            'token_type'   => 'bearer',
        ], 200);
    }

    /**
     * JWT TTL (minutes) → seconds.
     */
    private function getTtlSeconds(): int
    {
        // tymon/jwt-auth: same factory API
        return auth('api')->factory()->getTTL() * 60;
    }

    /**
     * Normalize the user for API responses.
     */
    private function transformUser($user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name ?? null,
            'email' => $user->email ?? null,
        ];
    }

    /**
     * Extract role names from the user model.
     * Supports spatie/laravel-permission via getRoleNames(),
     * JSON/array "roles" attribute, or CSV fallback.
     */
    private function extractRoles($user): array
    {
        if (! $user) {
            return [];
        }

        // Spatie Permission
        if (method_exists($user, 'getRoleNames')) {
            try {
                return $user->getRoleNames()->values()->all();
            } catch (Throwable $e) {
                // Log the error but do not expose it to the client
                logger(
                    'Error extracting roles from user model',
                    [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'user_id' => $user->id,
                    ]
                );
                // If there's an error, we can return an empty array or fallback to other methods
                return [];
            }
        }

        // Generic "roles" attribute – array or JSON or CSV
        $roles = data_get($user, 'roles');

        if (is_array($roles)) {
            return array_values($roles);
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values($decoded);
            }

            // CSV fallback: "admin,editor"
            return array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(',', $roles)
                    )
                )
            );
        }

        return [];
    }
}
