<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreThemeRequest;
use App\Http\Requests\Admin\UpdateThemeRequest;
use App\Http\Resources\ThemeResource;
use App\Models\Tenant;
use App\Models\Theme;
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
 * ThemeController
 *
 * Purpose:
 * Provide admin CRUD for per-tenant Themes with simple filtering and JSON support.
 *
 * Filters:
 * - tenant_id: limit themes to a tenant
 * - status: active|draft|archived
 * - q: search by name/slug
 */
class ThemeController extends Controller
{
  /**
   * List themes (paginated).
   *
   * @param  Request  $request
   * @return Response|View|Factory|AnonymousResourceCollection
   * @throws AuthorizationException
   */
  public function index(Request $request): Response|View|Factory|AnonymousResourceCollection
  {
    $this->authorize('viewAny', Theme::class);

    $themes = Theme::query()
      ->when($request->filled('tenant_id'), fn ($q) =>
      $q->where('tenant_id', $request->integer('tenant_id'))
      )
      ->when($request->filled('status'), fn ($q) =>
      $q->where('status', $request->string('status')->toString())
      )
      ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%' . $request->string('q')->toString() . '%';
        $q->where(function ($qq) use ($term) {
          $qq->where('name', 'like', $term)
            ->orWhere('slug', 'like', $term);
        });
      })
      ->orderByDesc('id')
      ->paginate($request->integer('per_page', 15));

    if ($request->expectsJson()) {
      return ThemeResource::collection($themes);
    }

    // Optional: pass tenants list for filter dropdown in the view.
    $tenants = Tenant::query()->select('id', 'name')->orderBy('name')->get();

    return response()->view('admin.themes.index', [
      'themes'  => $themes,
      'tenants' => $tenants,
    ]);
  }

  /**
   * Show create form.
   * @return Response|View|Factory
   * @throws AuthorizationException
   */
  public function create(): Response|View|Factory
  {
    $this->authorize('create', Theme::class);

    $tenants = Tenant::query()->select('id', 'name')->orderBy('name')->get();

    return response()->view('admin.themes.create', [
      'tenants' => $tenants,
    ]);
  }

  /**
   * Store a new theme.
   *
   * @param  StoreThemeRequest  $request
   * @return RedirectResponse|JsonResponse
   * @throws AuthorizationException
   */
  public function store(StoreThemeRequest $request): RedirectResponse|JsonResponse
  {
    $this->authorize('create', Theme::class);

    $data = $request->validated();

    /** @var Theme $theme */
    $theme = DB::transaction(fn () => Theme::query()->create($data));

    if ($request->expectsJson()) {
      return ThemeResource::make($theme)->response()->setStatusCode(201);
    }

    return redirect()
      ->route('admin.themes.show', $theme)
      ->with('success', 'Theme created successfully.');
  }

  /**
   * Show a theme.
   *
   * @param  Theme  $theme
   * @param  Request  $request
   * @return Response|View|Factory|JsonResponse|ThemeResource
   * @throws AuthorizationException
   */
  public function show(
    Theme $theme,
    Request $request
  ): Response|View|Factory|JsonResponse|ThemeResource
  {
    $this->authorize('view', $theme);

    if ($request->expectsJson()) {
      return ThemeResource::make($theme);
    }

    return response()->view('admin.themes.show', [
      'theme' => $theme,
    ]);
  }

  /**
   * Show edit form.
   * @param  Theme  $theme
   * @return Response|View|Factory
   * @throws AuthorizationException
   */
  public function edit(Theme $theme): Response|View|Factory
  {
    $this->authorize('update', $theme);

    $tenants = Tenant::query()->select('id', 'name')->orderBy('name')->get();

    return response()->view('admin.themes.edit', [
      'theme'   => $theme,
      'tenants' => $tenants,
    ]);
  }

  /**
   * Update a theme.
   *
   * @param  UpdateThemeRequest  $request
   * @param  Theme  $theme
   * @return RedirectResponse|JsonResponse
   * @throws AuthorizationException
   */
  public function update(UpdateThemeRequest $request, Theme $theme): RedirectResponse|JsonResponse
  {
    $this->authorize('update', $theme);

    $data = $request->validated();

    // Defense in depth: keep slug and tenant_id immutable here too
    unset($data['slug']);
    unset($data['tenant_id']);

    DB::transaction(fn () => $theme->update($data));

    if ($request->expectsJson()) {
      return ThemeResource::make($theme->fresh())->response();
    }

    return redirect()
      ->route('admin.themes.show', $theme)
      ->with('success', 'Theme updated successfully.');
  }

  /**
   * Soft-delete a theme.
   *
   * @param  Theme  $theme
   * @param  Request  $request
   * @return RedirectResponse|Response|JsonResponse
   * @throws AuthorizationException
   */
  public function destroy(Theme $theme, Request $request): Response|RedirectResponse|JsonResponse
  {
    $this->authorize('delete', $theme);

    $theme->delete();

    if ($request->expectsJson()) {
      return response()->json(['deleted' => true]);
    }

    return redirect()
      ->route('admin.themes.index')
      ->with('success', 'Theme deleted successfully.');
  }
}
