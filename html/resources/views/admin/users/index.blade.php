@php use App\Models\User; @endphp
@extends('admin.layouts.app')
@section('title','Users')

@section('content')
@php
  /** @var User|null $actualUser */
  $actualUser = auth()->user();
  $isSA = $actualUser?->isPlatformSuperAdmin();
  $limitReached = !empty($tenant) && !empty($seats) && ($seats['available'] <= 0);
  // Attempt to deduce tenant_id if not explicitly provided
  $tenantId = $tenantId
      ?? (isset($tenant) && $tenant->id ? $tenant->id : null)
      ?? (auth()->user()?->tenant_id ?? null);
@endphp

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Users</h1>
@include('admin.partials.seats-cta', [
  'limitReached' => $limitReached,
  'primaryRoute' => route('admin.users.create'),
  'primaryLabel' => 'Invite / Create User',
  'buyMore'      => 'Buy More Users',
  'tenantId'     => $tenantId,
  '$isSA'        => $isSA,
])
  </div>

@if(!empty($tenant) && !empty($seats))
  <div class="alert alert-info non-dismissible d-flex justify-content-between align-items-center">
    <div>
      <strong>Seats:</strong> {{ $seats['used'] }} / {{ $seats['limit'] }}
      <span class="text-muted ms-2">Available: {{ $seats['available'] }}</span>
    </div>
@if($seats['available'] <= 0)
    <span class="badge text-bg-warning">Limit reached</span>
@endif
  </div>
@endif

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>User</th>
@if($isSA)
            <th>Tenant</th>
@endif
            <th>Roles</th>
            <th>Status</th>
            <th class="text-end" style="width:36%">Actions</th>
          </tr>
        </thead>
        <tbody>
@forelse($users as $u)
          <tr>
            <td>
              <div class="fw-semibold">{{ $u->name ?? '—' }}</div>
              <div class="text-muted small">{{ $u->email }}</div>
            </td>

@if($isSA)
            <td>{{ optional($u->tenant)->name ?? '—' }}</td>
@endif

            <td>
@forelse($u->roles as $r)
              <span class="badge text-bg-light border me-1 mb-1">{{ $r->name }}</span>
@empty
              <span class="text-muted small">No roles</span>
@endforelse
            </td>
            <td>
@php
  $status = $u->status ?? 'active';
  $badge  = match($status) {
    'active'    => 'success',
    'suspended' => 'warning',
    'locked'    => 'danger',
    default     => 'secondary'
  };
@endphp
              <span class="badge text-bg-{{ $badge }}">{{ $status }}</span>
@if(!$u->hasVerifiedEmail())
              <span class="badge text-bg-secondary ms-1"
                    title="Email not verified">Unverified</span>
@endif
            </td>

            <td class="text-end">
              <div class="d-flex flex-wrap justify-content-end gap-1">

{{-- Manage roles --}}
@can('manage-user-roles', $u)
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('admin.users.roles.edit', $u) }}">
                  Manage roles
                </a>
@endcan

{{-- Estado: Activate / Suspend / Lock / Delete --}}
@can('manage-user-status', $u)
@if($u->status !== 'active')
                <form method="post"
                      action="{{ route('admin.users.status.update', $u) }}"
                      onsubmit="return confirm('Activate {{ $u->email }}?');">
@csrf
                  <input type="hidden" name="status" value="active">
                  <button class="btn btn-sm btn-success">Activate</button>
                </form>
@endif

@if($u->status !== 'suspended')
                <form method="post" action="{{ route('admin.users.status.update', $u) }}"
                      onsubmit="return confirm('Suspend {{ $u->email }}?');">
@csrf
                  <input type="hidden" name="status" value="suspended">
                  <button class="btn btn-sm btn-outline-warning">Suspend</button>
                </form>
@endif

@if($u->status !== 'locked')
                <form method="post"
                      action="{{ route('admin.users.status.update', $u) }}"
                      onsubmit="return confirm('Lock {{ $u->email }}?');">
@csrf
                  <input type="hidden" name="status" value="locked">
                  <button class="btn btn-sm btn-outline-danger">Lock</button>
                </form>
@endif

                <form method="post"
                      action="{{ route('admin.users.destroy', $u) }}"
                      onsubmit="return confirm(
                        'Delete {{ $u->email }} permanently? This cannot be undone.'
                        );">
@csrf
@method('DELETE')
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
@endcan

{{-- Impersonate --}}
@can('impersonate-user', $u)
                  <form method="post"
                        action="{{ route('admin.impersonate.start', $u) }}"
                        class="d-inline">
@csrf
                    <button class="btn btn-sm btn-outline-dark">Impersonate</button>
                  </form>
@endcan
              </div>
            </td>
          </tr>
@empty
          <tr>
            <td colspan="{{ $isSA ? 5 : 4 }}"
                class="text-center text-muted py-4">No users found.</td>
          </tr>
@endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $users->links() }}
    </div>
  </div>
@endsection
