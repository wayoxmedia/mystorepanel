<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
            'email'    => ['required', 'string', 'email:rfc', 'min:6', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:50'],
        ]);

        $credentials = [
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ];

        try {
            $token = auth('api')->attempt($credentials);

            if (!$token) {
                return response()->json(
                    ['error' => 'Unauthorized - Please check your credentials.'],
                    401
                );
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
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Authentication failed.',
            ], 500);
        }
    }

    /**
     * Logout the user.
     *
     * This endpoint is protected by auth:api middleware.
     *
     * It will invalidate the current token and return a 204 No Content response.
     *
     * If the user is already logged out, it will log the exception but not return it to the client.
     *
     * This prevents leaking sensitive information and keeps the logout process idempotent.
     *
     * If the user is already logged out, we can safely ignore this.
     *
     * This is a common practice to avoid exposing internal errors.
     * The user will still receive a 204 No Content response.
     *
     * @return Response|JsonResponse
     */
    public function logout(): Response|JsonResponse
    {
        try {
            auth('api')->logout();

            return response()->json([
                'message' => 'Logged out',
                ], 200);
            // return response()->noContent(); // 204
        } catch (Exception $e) {
            logger(
                'Logout exception at AuthController@logout',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth('api')->id(),
                ]
            );
            return response()->json([
                'error' => 'Logout failed',
            ], 500);
        }
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

        return response()->json([
                'user'  => $this->transformUser($user),
                'roles' => $this->extractRoles($user),
            ], 200);
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
            'id'         => $user->id,
            'name'       => $user->name ?? null,
            'email'      => $user->email ?? null,
            'tenant_id'  => $user->tenant_id,
            'created_at' => optional($user->created_at)?->toISOString(),
            'updated_at' => optional($user->updated_at)?->toISOString(),
        ];
    }

    /**
     * Extract role names from the user model.
     * Supports:
     *  - spatie getRoleNames()
     *  - "roles" (array/JSON/CSV)
     *  - "role"  (single string, or CSV/pipe like "admin|editor")
     */
    private function extractRoles($user): array
    {
        if (! $user) {
            return [];
        }

        // Normalizer: trim, cast to string, remove empties, dedupe case-insensitive
        $normalize = static function ($items): array {
            $items = is_array($items) ? $items : [$items];

            $clean = [];
            foreach ($items as $item) {
                if ($item === null) {
                    continue;
                }
                $val = is_string($item) ? trim($item) : trim((string) $item);
                if ($val !== '') {
                    $clean[] = $val;
                }
            }

            $seen = [];
            $unique = [];
            foreach ($clean as $role) {
                $key = mb_strtolower($role);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique[] = $role; // preserve original casing of first occurrence
                }
            }

            return array_values($unique);
        };

        // 1) Spatie Permission
        if (method_exists($user, 'getRoleNames')) {
            try {
                // Spatie return a Collection of strings
                return $normalize($user->getRoleNames()->all());
            } catch (Throwable $e) {
                logger()->warning('Error extracting roles via Spatie', [
                    'user_id' => $user->id ?? null,
                    'error'   => $e->getMessage(),
                ]);
                // Continue to the next methods
            }
        }

        // 2) Relation Eloquent roles() (p.ej. $user->roles->pluck('name'))
        if (method_exists($user, 'roles')) {
            try {
                $rolesRel = $user->roles; // May return a Collection or null
                if ($rolesRel instanceof Collection) {
                    $names = $rolesRel->map(function ($r) {
                        // Try with name, then role, then toString
                        return $r->name ?? $r->role ?? (is_scalar($r) ? (string) $r : null);
                    })->filter()->all();

                    if (! empty($names)) {
                        return $normalize($names);
                    }
                }
            } catch (Throwable $e) {
                logger()->warning('Error extracting roles via relation', [
                    'user_id' => $user->id ?? null,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 3) Attribute "roles" (array/JSON/CSV/Collection) or fallback "role"
        $roles = data_get($user, 'roles');
        if (empty($roles)) {
            $roles = data_get($user, 'role');
        }

        // If a collection, normalize it
        if ($roles instanceof Collection) {
            return $normalize($roles->all());
        }

        // If it's an array, normalize it
        if (is_array($roles)) {
            return $normalize($roles);
        }

        // If it's a string, try to decode JSON first,
        // if not, parse as CSV or pipe-delimited
        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $normalize($decoded);
            }

            $parts = preg_split('/[|,]/', $roles, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return $normalize($parts);
        }

        // Anything else (null, int, etc.) is not a valid role
        // so we return an empty array
        return [];
    }
}
