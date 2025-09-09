<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;



/**
 * Controller for managing user invitations within a multi-tenant application.
 *
 * Handles listing, resending, cancelling, and creating invitations.
 */
class InvitationController extends Controller
{
  /**
   * List pending/accepted/cancelled/expired invitations.
   * - Platform SA: puede ver todas y filtrar por tenant.
   * - Tenant Owner/Admin: solo ve las de su tenant.
   * Filtros:
   *   - ?status=pending|accepted|cancelled|expired|all  (default: pending)
   *   - ?tenant_id=ID   (solo para Platform SA)
   *   - ?include_expired=1  (cuando status=pending, incluye vencidas)
   */
  public function index(Request $request): View
  {
    $actor = $request->user();
    $query = Invitation::query()->orderByDesc('created_at');

    if (! $actor->isPlatformSuperAdmin()) {
      $query->where('tenant_id', $actor->tenant_id);
    } elseif ($request->filled('tenant_id')) {
      $query->where('tenant_id', $request->integer('tenant_id'));
    }

    $status = $request->string('status')->lower()->value();
    if ($status === '') {
      $status = 'pending';
    }

    if ($status !== 'all') {
      $query->where('status', $status);
    }

    if ($status === 'pending' && ! $request->boolean('include_expired')) {
      $query->where(function ($q) {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
      });
    }

    $invitations = $query->paginate(15)->withQueryString();
    $tenant = $actor->tenant;
    $seats  = $tenant ? [
      'limit'     => $tenant->seatsLimit(),
      'used'      => $tenant->seatsUsed(),
      'available' => max(0, $tenant->seatsLimit() - $tenant->seatsUsed()),
    ] : null;

    return view('admin.invitations.index', [
      'invitations' => $invitations,
      'status' => $status,
      'tenant' => $tenant,
      'seats' => $seats,
      ]);
  }

  /**
   * Resend (refresh) an invitation.
   * - Si no está pendiente o está vencida: regenera token, pone status=pending y extiende vigencia.
   * - Si sigue vigente: solo extiende vigencia.
   * Nota: el envío de correo se deja para el mailable/notificación (próximo archivo).
   */
  public function resend(Request $request, Invitation $invitation): RedirectResponse
  {
    $actor = $request->user();
    if (! $this->canManage($actor, $invitation)) {
      abort(403);
    }

    if ($invitation->status === 'accepted') {
      return back()->with('error', 'This invitation was already accepted.');
    }

    // Cooldown (minutes) — configurable; default 5 mins
    $cooldownMinutes = (int) config('mystore.invitations.cooldown_minutes', 5);

    if ($cooldownMinutes > 0 && $invitation->last_sent_at) {
      $lastSent = Carbon::parse($invitation->last_sent_at);
      $nextAllowed = $lastSent->copy()->addMinutes($cooldownMinutes);

      if (now()->lt($nextAllowed)) {
        $secondsLeft = now()->diffInSeconds($nextAllowed);
        $minutesLeft = (int) ceil($secondsLeft / 60);
        return back()->with(
          'error',
          "Please wait {$minutesLeft} minute(s) before resending this invitation."
        );
      }
    }

    $shouldReopen = $invitation->status !== 'pending'
      || ($invitation->expires_at && Carbon::parse($invitation->expires_at)->isPast());

    if ($shouldReopen) {
      $invitation->token      = Str::random(64);
      $invitation->status     = 'pending';
    }
    // Keep pending status and just extend expiration
    $invitation->expires_at = now()->addHours(config('mystore.invitations.expires_hours', 168)); // 7 días
    $invitation->save();

    Mail::to($invitation->email)
      ->send(new InvitationMail($invitation));

    // Update sending metrics
    $invitation->last_sent_at = now();
    $invitation->send_count   = (int) $invitation->send_count + 1;
    $invitation->save();

    // (Opcional) URL de aceptación, para el mailable
    // $acceptUrl = route('invitations.accept', ['token' => $invitation->token]);

    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'invite.resent',
      'subject_type' => Invitation::class,
      'subject_id'   => $invitation->id,
      'meta'         => [
        'email' => $invitation->email,
        'send_count' => $invitation->send_count
      ],
    ]);

    // TODO: enviar email aquí con un Mailable/Notification (siguiente archivo).
    return back()->with('success', 'Invitation refreshed and resent.');
  }

  /**
   * Cancel an invitation (frees the seat by leaving status!=pending).
   */
  public function cancel(Request $request, Invitation $invitation): RedirectResponse
  {
    $actor = $request->user();
    if (! $this->canManage($actor, $invitation)) {
      abort(403);
    }

    if (in_array($invitation->status, ['accepted', 'cancelled'], true)) {
      return back()->with('error', 'Invitation is not cancellable.');
    }

    $invitation->status     = 'cancelled';
    $invitation->expires_at = now();
    $invitation->save();

    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'invite.cancelled',
      'subject_type' => Invitation::class,
      'subject_id'   => $invitation->id,
      'meta'         => ['email' => $invitation->email],
    ]);

    return back()->with('success', 'Invitation cancelled.');
  }

  /** ------- Helpers ------- */
  private function canManage(User $actor, Invitation $inv): bool
  {
    if ($actor->isPlatformSuperAdmin()) {
      return true;
    }
    if (! $actor->hasAnyRole(['tenant_owner', 'tenant_admin'])) {
      return false;
    }
    return (int) $actor->tenant_id === (int) $inv->tenant_id;
  }
}
