<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
  /**
   * Check the request token against the expected value.
   *
   * Optional header token; set HEALTH_TOKEN in .env to require it.
   * @param  Request  $request
   * @return bool
   */
  private function checkToken(Request $request): bool
  {
    $expected = (string) env('HEALTH_TOKEN', '');
    if ($expected === '') return true; // no token required
    return hash_equals(
      $expected,
      (string) $request->header('X-Health-Token', '')
    );
  }

  /**
   * Liveness: basic app check.
   * @param  Request  $request
   * @return JsonResponse
   */
  public function live(Request $request): JsonResponse
  {
    if ( $this->checkToken($request)) {
      return response()->json(
        ['status' => 'forbidden'],
        403
      );
    }

    return response()->json([
      'status' => 'ok',
      'service' => 'mystorepanel',
      'env' => app()->environment(),
      'time' => now()->toIso8601String(),
    ]);
  }

  /**
   * Readiness: quick checks of dependencies.
   * @param  Request  $request
   * @return JsonResponse
   */
  public function ready(Request $request): JsonResponse
  {
    if (! $this->checkToken($request)) {
      return response()->json(
        ['status' => 'forbidden'],
        403
      );
    }

    $checks = [
      'db'        => false,
      'cache'     => false,
      'storage'   => false,
      'scheduler' => false,
      'queue'     => false,
    ];

    $errors = [];

    // DB check
    try {
      DB::select('select 1');
      $checks['db'] = true;
    } catch (Throwable $e) {
      $errors['db'] = $e->getMessage();
    }

    // Cache check (set/get ephemeral key)
    try {
      $key = 'health:ping';
      Cache::put($key, 'pong', 60);
      $checks['cache'] = Cache::get($key) === 'pong';
      if (! $checks['cache']) $errors['cache'] = 'cache_miss';
    } catch (Throwable $e) {
      $errors['cache'] = $e->getMessage();
    }

    // Storage check (writable)
    try {
      $dir = storage_path('framework');
      $checks['storage'] = is_writable($dir);
      if (! $checks['storage']) $errors['storage'] = "$dir not writable";
    } catch (Throwable $e) {
      $errors['storage'] = $e->getMessage();
    }

    // Scheduler heartbeat (esperamos un tick en ≤ 3 minutos)
    try {
      $ts = Cache::get('health:scheduler_beat');
      $diffInSecondsSch = now()->diffInSeconds(
        Carbon::createFromTimestamp((int)$ts)
      );
      $checks['scheduler'] = is_numeric($ts) && $diffInSecondsSch <= 180;
      if (! $checks['scheduler']) {
        $errors['scheduler'] = 'no recent heartbeat';
      }
    } catch (Throwable $e) {
      $errors['scheduler'] = $e->getMessage();
    }

    // Queue heartbeat (esperamos tick en ≤ 3 minutos)
    try {
      $qts = cache()->get('health:queue_beat');
      $diffInSecondsKu = now()->diffInSeconds(
        Carbon::createFromTimestamp((int)$qts));
      $checks['queue'] = is_numeric($qts) && $diffInSecondsKu <= 180;
      if (! $checks['queue']) $errors['queue'] = 'no recent queue heartbeat';
    } catch (Throwable $e) {
      $errors['queue'] = $e->getMessage();
    }


    $ok = $checks['db']
      && $checks['cache']
      && $checks['storage']
      && ($checks['scheduler'] ?? false)
      && ($checks['queue'] ?? false);

    return response()->json([
      'status'  => $ok ? 'ok' : 'degraded',
      'service' => 'mystorepanel',
      'env'     => app()->environment(),
      'time'    => now()->toIso8601String(),
      'checks'  => $checks,
      'errors'  => $errors ?: null,
    ], $ok ? 200 : 503);
  }
}
