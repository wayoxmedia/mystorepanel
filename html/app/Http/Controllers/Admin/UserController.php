<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

    $query = User::query()->with(['tenant', 'roles']);

    // Scope by tenant if not platform super admin
    if (! auth()->user()->isPlatformSuperAdmin()) {
      $query->where('tenant_id', auth()->user()->tenant_id);
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
      $query->whereHas('roles', fn ($qr) => $qr->where('slug', $request->string('role')));
    }

    if ($request->filled('q')) {
      $q = $request->string('q');
      $query->where(function ($qr) use ($q) {
        $qr->where('name', 'like', "%{$q}%")
          ->orWhere('email', 'like', "%{$q}%");
      });
    }

    $users = $query->orderByDesc('id')->paginate(15)->withQueryString();
    $tenants = auth()->user()->isPlatformSuperAdmin()
      ? Tenant::query()->orderBy('name')->get(['id','name'])
      : Tenant::query()->where('id', auth()->user()->tenant_id)->get(['id','name']);
    $roles = Role::query()->orderBy('name')->get(['slug','name']);

    return view('admin.users.index', compact('users', 'tenants', 'roles'));
  }

  /**
   * Show the form for creating a new user.
   *
   * @return Factory|View|Application
   */
  public function create(): Factory|Application|View
  {
    $this->authorizeCreate();

    $tenants = auth()->user()->isPlatformSuperAdmin()
      ? Tenant::query()->orderBy('name')->get(['id','name'])
      : Tenant::query()->where('id', auth()->user()->tenant_id)->get(['id','name']);

    $roles = Role::query()->orderBy('name')->get(['slug','name']);

    return view('admin.users.create', compact('tenants', 'roles'));
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
    $this->authorizeCreate();

    $mode = $request->string('mode'); // create|invite
    $roleSlug = $request->string('role_slug');
    $tenantId = $this->resolveTenantId($request->input('tenant_id'));

    if ($mode == 'invite') {
      // Create invitation only
      $token = Str::random(64);
      $inv = Invitation::query()->create([
        'email'      => $request->string('email'),
        'tenant_id'  => $tenantId,
        'role_id'    => Role::query()->where('slug', $roleSlug)->value('id'),
        'token'      => $token,
        'expires_at' => now()->addHours(config('mystore.invitations.expires_hours')),
        'status'     => 'pending',
        'invited_by' => auth()->id(),
      ]);

      AuditLog::query()->create([
        'actor_id'     => auth()->id(),
        'action'       => 'user.invited',
        'subject_type' => Invitation::class,
        'subject_id'   => $inv->id,
        'meta'         => [
          'email' => $inv->email,
          'tenant_id' => $tenantId,
          'role_slug' => $roleSlug,
        ],
      ]);

      // TODO: send notification email with acceptance link
      // $acceptUrl = route('invitations.accept', ['token' => $token]);

      return redirect()
        ->route('admin.users.index')
        ->with('success', 'Invitation created successfully.');
    }

    // mode === 'create' → create user immediately
    $user = new User();
    $user->name = $request->string('name');
    $user->email = $request->string('email');
    $user->tenant_id = $tenantId;
    $user->status = 'active';
    $user->password = bcrypt($request->string('password'));
    $user->save();

    $roleId = Role::query()->where('slug', $roleSlug)->value('id');
    if ($roleId) {
      $user->roles()->syncWithoutDetaching([$roleId]);
    }

    AuditLog::query()->create([
      'actor_id'     => auth()->id(),
      'action'       => 'user.created',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => [
        'tenant_id' => $tenantId,
        'role_slug' => $roleSlug,
      ],
    ]);

    return redirect()
      ->route('admin.users.index')
      ->with('success', 'User created successfully.');
  }

  /** ----- helpers & authorization wrappers ----- */

  /**
   * Resolve tenant ID based on the current user's permissions.
   * Platform super admins can create users for any tenant, while tenant managers
   * are restricted to their own tenant.
   *
   * @param $input
   * @return int|null
   */
  private function resolveTenantId($input): ?int
  {
    if (auth()->user()->isPlatformSuperAdmin()) {
      return $input ? (int) $input : null; // platform staff can be null (no tenant)
    }
    // Tenant managers must stick to their own tenant
    return auth()->user()->tenant_id;
  }

  /**
   * Authorize viewing any users.
   * Platform super admins can view all users, while tenant managers can only view users in their tenant.
   *
   * @return void
   */
  private function authorizeViewAny(): void
  {
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
   * Platform super admins can create users for any tenant, while tenant managers
   * can only create users within their own tenant.
   *
   * @return void
   */
  private function authorizeCreate(): void
  {
    $user = auth()->user();
    if ($user->isPlatformSuperAdmin()) {
      return;
    }
    if (! Gate::allows('manage-tenant-users', $user->tenant_id)) {
      abort(403);
    }
  }
}
