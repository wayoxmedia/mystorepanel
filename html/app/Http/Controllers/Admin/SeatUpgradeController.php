<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for handling seat upgrade requests within a multi-tenant application.
 *
 * Allows Platform Super Admins and Tenant Owners/Admins to request an increase in user seats.
 * Requests are logged for auditing purposes.
 */
class SeatUpgradeController extends Controller
{
  /**
   * Página informativa para solicitar aumento de seats.
   * - SA: puede seleccionar tenant (?tenant_id=)
   * - Tenant Owner/Admin: su propio tenant
   * @param Request $request
   * @return View
   */
  public function show(Request $request): View
  {
    $actor = $request->user();

    if (! ($actor->isPlatformSuperAdmin() || $actor->hasAnyRole(['tenant_owner','tenant_admin']))) {
      abort(403);
    }

    $tenant = $actor->isPlatformSuperAdmin()
      ? Tenant::query()->find($request->integer('tenant_id')) // opcional
      : $actor->tenant;

    // Si SA no pasó tenant_id, no mostramos stats (solo landing)
    $seats = null;
    if ($tenant) {
      $limit = $tenant->seatsLimit();
      $used  = $tenant->seatsUsed();
      $seats = [
        'limit'     => $limit,
        'used'      => $used,
        'available' => max(0, $limit - $used),
      ];
    }

    return view('admin.seats.upgrade', compact('tenant', 'seats'));
  }

  /**
   * Enviar solicitud (placeholder). No cambia nada todavía.
   * Guarda auditoría y muestra un flash.
   * @param Request $request
   * @return RedirectResponse
   */
  public function request(Request $request): RedirectResponse
  {
    $actor = $request->user();

    if (! ($actor->isPlatformSuperAdmin() || $actor->hasAnyRole(['tenant_owner','tenant_admin']))) {
      abort(403);
    }

    $tenant = $actor->isPlatformSuperAdmin()
      ? Tenant::query()->findOrFail($request->integer('tenant_id'))
      : $actor->tenant;

    $currentLimit = $tenant->seatsLimit();
    $used         = $tenant->seatsUsed();

    $data = $request->validate([
      'desired_limit' => [
        'required',
        'integer',
        'min:'.max($used + 1, 1),
        'max:100000'
      ],
      'note'          => [
        'nullable',
        'string',
        'max:2000'
      ],
    ]);

    // Solo auditoría por ahora (no cambiamos el límite aquí)
    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'tenant.seats.upgrade.requested',
      'subject_type' => Tenant::class,
      'subject_id'   => $tenant->id,
      'meta'         => [
        'from'         => $currentLimit,
        'to_requested' => (int) $data['desired_limit'],
        'used'         => $used,
        'note'         => $data['note'] ?? null,
      ],
    ]);

    return back()->with('success', 'Your request was submitted. Our team will contact you shortly.');
  }
}
