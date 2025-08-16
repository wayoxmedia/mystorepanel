<?php

use Illuminate\Http\JsonResponse;

/**
 * Throttle Callback
 *
 * @param string $msg
 * @return JsonResponse
 */
function throttleCallback(string $msg): JsonResponse
{
    return response()->json(['message' => $msg], 429);
}
