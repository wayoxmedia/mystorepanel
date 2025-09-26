<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\Template;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * TenantController
 *
 * Purpose:
 * Provide admin CRUD for Tenants, enforcing validation and policy.
 * Includes suspend/resume actions that toggle the 'status' field.
 *
 * Assumptions:
 * - Tenant model exists and uses SoftDeletes.
 * - Store/Update requests already validate and authorize.
 * - 'status' in {'active','suspended','pending'}.
 *
 * Notes:
 * - Responses: JSON if the client expectsJson(); otherwise redirects with flashes.
 * - Adjust pagination and view names when wiring your UI (Blade/Inertia).
 */
class TenantController extends Controller
{
  /**
   * List tenants (paginated).
   *
   * @param  Request  $request
   * @return Response|AnonymousResourceCollection
   * @throws AuthorizationException
   */
  public function index(Request $request): Response|AnonymousResourceCollection
  {
    $this->authorize('viewAny', Tenant::class);

    $tenants = Tenant::query()
      ->when($request->filled('status'), function ($q) use ($request) {
        $q->where('status', $request->string('status')->toString());
      })
      ->when($request->filled('q'), function ($q) use ($request) {
        $q->where(function ($qq) use ($request) {
          $term = '%' . $request->string('q')->toString() . '%';
          $qq->where('name', 'like', $term)
            ->orWhere('slug', 'like', $term)
            ->orWhere('primary_domain', 'like', $term);
        });
      })
      ->orderByDesc('id')
      ->paginate($request->integer('per_page', 15));

    if ($request->expectsJson()) {
      return TenantResource::collection($tenants);
    }

    return response()->view('admin.tenants.index', [
      'tenants' => $tenants,
    ]);
  }

  /**
   * Store a new tenant.
   *
   * @param  StoreTenantRequest  $request
   * @return Response|RedirectResponse|JsonResponse
   * @throws AuthorizationException
   */
  public function store(StoreTenantRequest $request): Response|RedirectResponse|JsonResponse
  {
    $this->authorize('create', Tenant::class);

    $data = $request->validated();

    $tenant = DB::transaction(function () use ($data) {
      /** @var Tenant $tenant */
      $tenant = Tenant::query()->create($data);

      // TODO: seed defaults (sites/themes/settings) as needed.
      return $tenant;
    });

    if ($request->expectsJson()) {
      return response()->json($tenant, 201);
    }

    return redirect()
      ->route('admin.tenants.show', $tenant)
      ->with('success', 'Tenant created successfully.');
  }

  /**
   * Show a tenant.
   *
   * @param  Tenant  $tenant
   * @param  Request  $request
   * @return View|Factory|Response|TenantResource
   * @throws AuthorizationException
   */
  public function show(Tenant $tenant, Request $request): View|Factory|Response|TenantResource
  {
    $this->authorize('view', $tenant);

    if ($request->expectsJson()) {
      return TenantResource::make($tenant);
    }

    // TODO: replace 'admin.tenants.show' with your actual view.
    return response()->view('admin.tenants.show', [
      'tenant' => $tenant,
    ]);
  }

  /**
   * Update a tenant.
   *
   * @param  UpdateTenantRequest  $request
   * @param  Tenant  $tenant
   * @return JsonResponse|RedirectResponse
   * @throws AuthorizationException
   */
  public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse|RedirectResponse
  {
    $this->authorize('update', $tenant);

    $data = $request->validated();

    // Enforce immutable slug at controller level too (defense in depth)
    if (isset($data['slug']) && $data['slug'] !== $tenant->slug) {
      unset($data['slug']);
    }

    DB::transaction(function () use ($tenant, $data) {
      $tenant->update($data);
    });

    if ($request->expectsJson()) {
      return response()->json($tenant->fresh());
    }

    return redirect()
      ->route('admin.tenants.show', $tenant)
      ->with('success', 'Tenant updated successfully.');
  }

  /**
   * Soft-delete a tenant.
   *
   * @param  Tenant  $tenant
   * @param  Request  $request
   * @return JsonResponse|RedirectResponse
   * @throws AuthorizationException
   */
  public function destroy(Tenant $tenant, Request $request): JsonResponse|RedirectResponse
  {
    $this->authorize('delete', $tenant);

    $tenant->delete();

    if ($request->expectsJson()) {
      return response()->json(['deleted' => true]);
    }

    return redirect()
      ->route('admin.tenants.index')
      ->with('success', 'Tenant deleted successfully.');
  }

  /**
   * Suspend a tenant (status → 'suspended').
   *
   * @param  Tenant  $tenant
   * @param  Request  $request
   * @return JsonResponse|RedirectResponse
   * @throws AuthorizationException
   */
  public function suspend(Tenant $tenant, Request $request): JsonResponse|RedirectResponse
  {
    $this->authorize('suspend', $tenant);

    if ($tenant->status !== 'suspended') {
      $tenant->update(['status' => 'suspended']);
    }

    if ($request->expectsJson()) {
      return response()->json(['status' => $tenant->status]);
    }

    return back()->with('success', 'Tenant suspended.');
  }

  /**
   * Resume a tenant (status → 'active').
   *
   * @param  Tenant  $tenant
   * @param  Request  $request
   * @return JsonResponse|RedirectResponse
   * @throws AuthorizationException
   */
  public function resume(Tenant $tenant, Request $request): JsonResponse|RedirectResponse
  {
    $this->authorize('resume', $tenant);

    if ($tenant->status !== 'active') {
      $tenant->update(['status' => 'active']);
    }

    if ($request->expectsJson()) {
      return response()->json(['status' => $tenant->status]);
    }

    return back()->with('success', 'Tenant resumed.');
  }

  /**
   * Show the create form for a new tenant.
   *
   * @return View|Factory|Response
   * @throws AuthorizationException
   */
  public function create(): View|Factory|Response
  {
    $this->authorize('create', Tenant::class);

    // If you have a templates table, pass a lightweight list to the view.
    $templates = class_exists(Template::class)
      ? Template::query()->select('id', 'name')->orderBy('name')->get()
      : collect();

    return response()->view('admin.tenants.create', [
      'templates' => $templates,
    ]);
  }

  /**
   * Show the edit form for an existing tenant.
   *
   * @param  Tenant  $tenant
   * @return View|Factory|Response
   * @throws AuthorizationException
   */
  public function edit(Tenant $tenant): View|Factory|Response
  {
    $this->authorize('update', $tenant);

    $templates = class_exists(Template::class)
      ? Template::query()->select('id', 'name')->orderBy('name')->get()
      : collect();

    return response()->view('admin.tenants.edit', [
      'tenant'    => $tenant,
      'templates' => $templates,
    ]);
  }
}
