<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for managing tenant user seats within a multi-tenant application.
 *
 * Handles listing tenants with their seat usage and limits, and updating seat limits.
 */
class TenantSeatsController extends Controller
{
  /**
   * List tenants with seats usage and limits (only Platform SA).
   * @param Request $request
   * @return View
   */
  public function index(Request $request): View
  {
    $actor = $request->user();
    if (! $actor->isPlatformSuperAdmin()) {
      abort(403);
    }

    $q = (string) $request->string('q');

    $tenants = Tenant::query()
      ->when($q !== '', function ($qb) use ($q) {
        $qb->where('name', 'like', "%{$q}%")
          ->orWhere('slug', 'like', "%{$q}%");
      })
      ->orderBy('name')
      ->paginate(20)
      ->withQueryString();

    // pre calculate seats used for display
    $stats = [];
    foreach ($tenants as $t) {
      $stats[$t->id] = [
        'limit' => $t->seatsLimit(),
        'used'  => $t->seatsUsed(),
      ];
    }

    return view('admin.tenants.seats', compact('tenants', 'stats', 'q'));
  }

  /**
   * Update the user seat limit for a tenant.
   *
   * Only Platform SA can perform this action.
   * Validates that the new limit is not lower than the seats currently used.
   * Logs the change in the audit log.
   * @param Request $request
   * @param Tenant $tenant
   * @return RedirectResponse
   */
  public function update(Request $request, Tenant $tenant): RedirectResponse
  {
    $actor = $request->user();
    if (! $actor->isPlatformSuperAdmin()) {
      abort(403);
    }

    $validated = $request->validate([
      'user_seat_limit' => ['required', 'integer', 'min:1', 'max:10000'],
    ]);

    $newLimit = (int) $validated['user_seat_limit'];
    $used     = $tenant->seatsUsed();

    if ($newLimit < $used) {
      return back()->withErrors([
        "user_seat_limit_{$tenant->id}" =>
          "New limit ({$newLimit}) cannot be lower than seats used ({$used}).",
      ]);
    }

    $before = $tenant->seatsLimit();
    if ($before === $newLimit) {
      return back()->with('success', "No changes for {$tenant->name}.");
    }

    $tenant->user_seat_limit = $newLimit;
    $tenant->save();

    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'tenant.seats.updated',
      'subject_type' => Tenant::class,
      'subject_id'   => $tenant->id,
      'meta'         => ['from' => $before, 'to' => $newLimit],
    ]);

    return back()->with('success', "Seats updated for {$tenant->name} ({$before} → {$newLimit}).");
  }
}
