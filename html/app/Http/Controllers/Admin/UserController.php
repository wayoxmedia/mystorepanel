<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MailDispatch;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controller for managing users within a multi-tenant application.
 *
 * Handles listing, creating (directly or via invitation), and deleting users.
 * Supports role-based access control for platform super admins and tenant managers.
 */
class UserController extends Controller
{
  /**
   * Display a listing of the users.
   * This method handles both platform super admins and tenant managers.
   * It allows filtering by tenant, status, role, and search query.
   *
   * @param  Request  $request
   * @return Factory|View|Application
   */
  public function index(Request $request): Factory|Application|View
  {
    $this->authorizeViewAny();

    $query = User::query()->with(['tenant', 'role']);

    // Scope by tenant if not platform super admin
    /** @var User $user */
    $user = auth()->user();
    if (! $user->isPlatformSuperAdmin()) {
      $query->where('tenant_id', $user->tenant_id);
    } else {
      // Optional filters
      if ($request->filled('tenant_id')) {
        $query->where('tenant_id', $request->integer('tenant_id'));
      }
    }

    if ($request->filled('status')) {
      $query->where('status', $request->string('status'));
    }

    if ($request->filled('role')) {
      $query->whereHas(
        'role',
        fn ($qr) => $qr->where('slug', $request->string('role')));
    }

    if ($request->filled('q')) {
      $q = $request->string('q');
      $query->where(function ($qr) use ($q) {
        $qr->where('name', 'like', "%{$q}%")
          ->orWhere('email', 'like', "%{$q}%");
      });
    }

    $users = $query->orderByDesc('id')
      ->paginate(15)
      ->withQueryString();
    $tenants = $user->isPlatformSuperAdmin()
      ? Tenant::query()->orderBy('name')
        ->get(['id','name'])
      : Tenant::query()
        ->where('id', $user->tenant_id)
        ->get(['id','name']);
    $roles = Role::query()
      ->orderBy('id')
      ->get(['id', 'slug','name']);

    $tenant = $user->tenant; // null para Platform SA

    $seats = null;
    if ($tenant) {
      $seats = [
        'limit' => $tenant->seatsLimit(),
        'used'  => $tenant->seatsUsed(),
        'available' => max(0, $tenant->seatsLimit() - $tenant->seatsUsed()),
      ];
    }

    return view('admin.users.index', [
      'tenant' => $tenant,
      'seats'  => $seats,
      'tenants' => $tenants,
      'roles' => $roles,
      'users' => $users,
    ]);
  }

  /**
   * Show the form for creating a new user.
   *
   * @param  Request  $request
   * @return Factory|View|Application
   */
  public function create(Request $request): Factory|Application|View
  {
    $this->authorizeCreate();

    $actor = $request->user();

    // If SA, can choose tenant; otherwise, no tenant select in form,
    // si no, usará su tenant por defecto en el store()
    $tenants = $actor->isPlatformSuperAdmin()
      ? Tenant::query()
        ->orderBy('name')
        ->get(['id','name'])
      : collect();

    // Roles the actor can assign (UI)
    $roles = $this->assignableRolesFor($actor);

    return view(
      'admin.users.create',
      compact('tenants', ['roles', 'actor']));
  }

  /**
   * Store a newly created user or invitation.
   * Handles both direct user creation and invitation workflows.
   *
   * @param  StoreUserRequest  $request
   * @return RedirectResponse
   */
  public function store(StoreUserRequest $request): RedirectResponse
  {
    // TODO: Move logic to Service class
    $this->authorizeCreate();

    /** @var User $actor */
    $actor    = auth()->user();
    $mode     = (string) $request->string('mode');
    $roleSlug = (string) $request->string('role_slug');
    $tenantId = $this->resolveTenantId($request->input('tenant_id'));

    $role = Role::query()
      ->where('slug', $roleSlug)
      ->first();
    if (! $role) {
      return back()->withErrors(['role_slug' => 'Invalid role selected.']);
    }

    $isPlatformSA = $actor->isPlatformSuperAdmin();
    if (!$isPlatformSA) {
      if ($role->scope === 'platform' || $role->slug === 'platform_super_admin') {
        abort(403, 'You are not allowed to assign platform roles.');
      }
      // Cant create/invite out of your tenant
      if ((int) $tenantId !== (int) $actor->tenant_id) {
        abort(403, 'You can only manage users in your tenant.');
      }
    }

    // If no tenant ID and not platform SA, abort
    if (is_null($tenantId) && ! $isPlatformSA) {
      abort(403, 'Tenant is required.');
    }

    $email = (string) $request->string('email');

    if ($mode === 'invite') {
      // Para staff (tenant_id null) NO hay seats; para tenants, sí.
      if (! is_null($tenantId)) {
        try {
          DB::transaction(function () use (
            $request, $tenantId, $actor, $role, $email
          ) {
            // Check seats availability
            if (!$this->hasSeatsAvailable($tenantId)) {
              abort(422, 'No seats available for this tenant.');
            }

            // Avoid duplicates users by email
            if ($this->isRegisteredEmail($email)) {
              abort(422, 'Email already in use.');
            }

            // Avoid pending invitation
            if ($this->hasInvitationForTenantAndEmail($tenantId, $email)) {
              abort(
                422,
                'There is already a pending invitation for this email.');
            }

            $token = Str::random(64);

            $inv = Invitation::query()->create([
              'email'       => $email,
              'tenant_id'   => $tenantId,
              'role_id'     => $role->id,
              'token'       => $token,
              'expires_at'  => now()->addHours(
                config(
                  'mystore.invitations.expires_hours',
                  168)
              ), // 7d por defecto
              'status'      => 'pending',
              'invited_by'  => $actor->id,
              'last_sent_at'=> now(),
              'send_count'  => 1,
            ]);

            // Enviar correo de invitación
            MailDispatch::deliver(
              new InvitationMail($inv),
              $inv->email
            );

            AuditLog::query()->create([
              'actor_id'     => $actor->id,
              'action'       => 'user.invited',
              'subject_type' => Invitation::class,
              'subject_id'   => $inv->id,
              'meta'         => [
                'email'      => $email,
                'tenant_id'  => $tenantId,
                'role_slug'  => $role->slug,
                'send_count' => $inv->send_count,
              ],
            ]);

            // TODO: enviar email con link de aceptación
            // $acceptUrl = route('invitations.accept', ['token' => $token]);
          });
        } catch (Throwable $e) {
          return back()->withErrors(['invite' => $e->getMessage()]);
        }
      } else {
        // Invitación para staff de plataforma (sin seats)
        if ($this->isRegisteredEmail($email)) {
          return back()->withErrors(['email' => 'Email already in use.']);
        }

        $token = Str::random(64);
        $inv = Invitation::query()->create([
          'email'       => $email,
          'tenant_id'   => null,
          'role_id'     => $role->id,
          'token'       => $token,
          'expires_at'  => now()->addHours(
            config(
              'mystore.invitations.expires_hours',
              168)
          ),
          'status'      => 'pending',
          'invited_by'  => $actor->id,
          'last_sent_at'=> now(),
          'send_count'  => 1,
        ]);

        // Send invitation email
        MailDispatch::deliver(
          new InvitationMail($inv),
          $inv->email
        );

        AuditLog::query()->create([
          'actor_id'     => $actor->id,
          'action'       => 'user.invited',
          'subject_type' => Invitation::class,
          'subject_id'   => $inv->id,
          'meta'         => [
            'email'      => $inv->email,
            'tenant_id'  => null,
            'role_slug'  => $role->slug,
            'send_count' => $inv->send_count,
          ],
        ]);

        // TODO: enviar email con link de aceptación
      }

      return redirect()->route('admin.users.index')
        ->with('success', 'Invitation created successfully.')
        ->with('nd', 'non-dismissible'); // for persistent flash message
    }

    if ($mode === 'create') {
      // Staff (tenant_id null) no consume seats
      if (is_null($tenantId)) {
        if (! $isPlatformSA) {
          abort(403);
        }
        $user = new User();
        $user->name      = (string) $request->string('name');
        $user->email     = (string) $request->string('email');
        $user->tenant_id = null;
        $user->role_id   = $role->id;
        $user->status    = 'active';
        $user->password  = (string) $request->string('password');
        $user->password = Hash::make((string) $request->string('password'));
        $user->save();

        AuditLog::query()->create([
          'actor_id'     => $actor->id,
          'action'       => 'user.created',
          'subject_type' => User::class,
          'subject_id'   => $user->id,
          'meta'         => ['tenant_id' => null, 'role_slug' => $role->slug],
        ]);

        return redirect()
          ->route('admin.users.index')
          ->with('success', 'User created successfully.');
      }

      // Tenants: transacción with lock and seats validation
      try {
        DB::transaction(function () use (
          $request, $tenantId, $actor, $role, $email, &$user
        ) {
          // Check seats availability
          if (!$this->hasSeatsAvailable($tenantId)) {
            abort(422, 'No seats available for this tenant.');
          }

          // Avoid duplicates users by email
          if ($this->isRegisteredEmail($email)) {
            abort(422, 'Email already in use.');
          }

          // Avoid pending invitation
          if ($this->hasInvitationForTenantAndEmail($tenantId, $email)) {
            abort(422, 'There is a pending invitation for this email.');
          }

          // Create user
          $user = new User();
          $user->name      = (string) $request->string('name');
          $user->email     = $email;
          $user->tenant_id = $tenantId;
          $user->role_id   = $role->id;
          $user->status    = 'active';
          $user->password  = Hash::make((string) $request->string('password'));
          $user->save();

          AuditLog::query()->create([
            'actor_id'     => $actor->id,
            'action'       => 'user.created',
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'meta'         => [
              'email'     => $email,
              'tenant_id' => $tenantId,
              'role_slug' => $role->slug
            ],
          ]);
        });
      } catch (Throwable $e) {
        return back()->withErrors(['create' => $e->getMessage()]);
      }

      return redirect()
        ->route('admin.users.index')
        ->with('success', 'User created successfully.');
    }

    return back()->withErrors(['mode' => 'Invalid mode.']);
  }

  /**
   * Remove the specified user from storage.
   *
   * Only Platform SA or Tenant Owner/Admin of same tenant can delete users,
   * and Platform SA users cannot be deleted.
   *
   * Users cannot delete themselves.
   *
   * @param  User  $user
   * @return RedirectResponse
   */
  public function destroy(User $user): RedirectResponse
  {
    /** @var User $actor */
    $actor = auth()->user();

    // 1. Permission: Never delete Platform SA
    if ($user->isPlatformSuperAdmin()) {
      return back()->with('error', 'Platform Super Admins can not be deleted.');
    }

    // 2. Don't allow self-deletion
    if ($actor->id === $user->id) {
      return back()->with('error', 'You cannot delete yourself.');
    }

    // 3. Platform SA or Tenant Owner/Admin of same tenant
    $allowed = false;
    if ($actor->isPlatformSuperAdmin()) {
      $allowed = true;
    } elseif ($actor->hasAnyRole(['tenant_owner','tenant_admin'])) {
      $allowed = ((int) $actor->tenant_id === (int) $user->tenant_id);
    }

    if (! $allowed) {
      return back()->with('error', 'You are not allowed to delete this user.');
    }

    // 4. Never leave a tenant without an owner
    try {
      $tenantId = $user->tenant_id;
      if (! is_null($tenantId)) {
        // Is the user a tenant owner?
        $ownerRoleId = Role::query()
          ->where('slug', 'tenant_owner')
          ->value('id');
        if ($ownerRoleId) {
          $isTargetOwner = $ownerRoleId === $user->role_id;

          if ($isTargetOwner) {
            // Are there other owners in the same tenant? Including the target?
            $otherOwners = DB::table('role_user as ru')
              ->join('users as u', 'u.id', '=', 'ru.user_id')
              ->where('ru.role_id', $ownerRoleId)
              ->where('u.tenant_id', $tenantId)
              ->where('u.id', '!=', $user->id)
              ->count();

            if ($otherOwners === 0) {
              return back()
                ->with('error', 'You cannot delete the last Tenant Owner for this tenant.');
            }
          }
        }
      }
    } catch (Throwable $e) {
      // If something fails when checking the invariant, better prevent deletion
      Log::error('Error checking tenant owner invariant: '.$e->getMessage());
      return back()
        ->with('error', 'Unable to verify tenant owner invariant. User not deleted.');
    }

    // Hard delete
    $uid = $user->id;
    $email = $user->email;

    $user->forceDelete();

    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'user.deleted',
      'subject_type' => User::class,
      'subject_id'   => $uid,
      'meta'         => ['email' => $email, 'tenant_id' => $tenantId],
    ]);

    return back()->with('success', 'User deleted.');
  }

  /** ----- helpers & authorization wrappers ----- */

  /**
   * Resolve tenant ID based on the current user's permissions.
   * Platform super admins can create users for any tenant, while tenant managers
   * are restricted to their own tenant.
   *
   * @param mixed $input
   * @return integer|null
   */
  private function resolveTenantId(mixed $input): ?int
  {
    /** @var User $user */
    $user = auth()->user();
    if ($user->isPlatformSuperAdmin()) {
      return $input ? (int) $input : null; // platform staff can be null (no tenant)
    }
    // Tenant managers must stick to their own tenant
    return $user->tenant_id;
  }

  /**
   * Authorize viewing any users.
   * Platform super admins can view all users, while tenant managers can only view users in their tenant.
   *
   * @return void
   */
  private function authorizeViewAny(): void
  {
    /** @var User $user */
    $user = auth()->user();
    if ($user->isPlatformSuperAdmin()) {
      return;
    }
    if (! Gate::allows('manage-tenant-users', $user->tenant_id)) {
      abort(403);
    }
  }

  /**
   * Authorize creating a new user.
   *
   * Platform super admins can create users for any tenant, while tenant managers
   * can only create users within their own tenant.
   *
   * @return void
   */
  private function authorizeCreate(): void
  {
    /** @var User $user */
    $user = auth()->user();
    if ($user->isPlatformSuperAdmin()) {
      return;
    }
    if (! Gate::allows('manage-tenant-users', $user->tenant_id)) {
      abort(403);
    }
  }

  /**
   * Validate how many seats are used and available.
   *
   * @param  integer  $tenantId
   * @return boolean
   */
  private function hasSeatsAvailable(int $tenantId): bool
  {
    /** @var Tenant $tenant */
    $tenant = Tenant::query()
      ->whereKey($tenantId)
      ->lockForUpdate()
      ->firstOrFail();

    $used = User::query()
      ->where('tenant_id', $tenantId)
      ->whereIn('status', ['active', 'locked', 'suspended'])
      ->count();

    $pendingInvites = Invitation::query()
      ->where('tenant_id', $tenantId)
      ->where('status', 'pending')
      ->count();

    return ($used + $pendingInvites) <= $tenant->seatsLimit();
  }

  /**
   * Check for existing pending invitations for the same tenant and email.
   *
   * @param  integer  $tenantId
   * @param  string  $email
   * @return boolean
   */
  private function hasInvitationForTenantAndEmail(int $tenantId, string $email): bool
  {
    return Invitation::query()
      ->where('tenant_id', $tenantId)
      ->where('email', $email)
      ->where('status', 'pending')
      ->exists();
  }

  /**
   * Check if any user exists with the given email.
   *
   * @param  string $email
   * @return boolean
   */
  private function isRegisteredEmail(string $email): bool
  {
    return User::query()
      ->where('email', $email)
      ->exists();
  }

  /**
   * Get roles that the actor can assign to new users.
   *
   * Platform super admins can assign any role, while tenant managers
   * can only assign tenant-scoped roles.
   *
   * @param  User  $actor
   * @return Collection
   */
  private function assignableRolesFor(User $actor): Collection
  {
    if ($actor->isPlatformSuperAdmin()) {
      return Role::query()
        ->orderBy('id')
        ->get(['id','name','slug','scope']);
    }

    return Role::query()
      ->where('scope', 'tenant')
      ->where(
        'slug',
        '!=',
        'platform_super_admin') // redundant, for security
      ->orderBy('id')
      ->get(['id','name','slug','scope']);
  }
}
