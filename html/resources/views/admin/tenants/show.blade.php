{{--
  Tenant Show (Admin) — Bootstrap 5

  Purpose:
  - Display tenant details with quick actions (suspend/resume/delete).
  - Present metadata in a clean, readable layout.

  Assumptions:
  - Route names: admin.tenants.index, admin.tenants.suspend, admin.tenants.resume, admin.tenants.destroy
  - Bootstrap 5 CSS is loaded in your layout.
--}}

@extends('layouts.app')

@section('title', 'Tenant: ' . ($tenant->name ?? 'Detail'))

@section('content')
  <div class="container py-4">

    {{-- Flash messages --}}
    @if (session('success'))
      <div class="alert alert-success" role="alert">
        {{ session('success') }}
      </div>
    @endif

    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.tenants.index') }}">Tenants</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $tenant->name }}</li>
      </ol>
    </nav>

    <div class="row g-3">
      {{-- Details card --}}
      <div class="col-12 col-lg-8">
        <div class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
              <h2 class="h5 mb-0">{{ $tenant->name }}</h2>
              @php
                $badgeClass = match($tenant->status) {
                  'active'    => 'bg-success',
                  'suspended' => 'bg-warning text-dark',
                  default     => 'bg-secondary'
                };
              @endphp
              <span class="badge {{ $badgeClass }}">{{ ucfirst($tenant->status) }}</span>
            </div>
          </div>

          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <tbody>
                <tr>
                  <th scope="row" class="w-25">ID</th>
                  <td>{{ $tenant->id }}</td>
                </tr>
                <tr>
                  <th scope="row">Name</th>
                  <td>{{ $tenant->name }}</td>
                </tr>
                <tr>
                  <th scope="row">Slug</th>
                  <td><code>{{ $tenant->slug }}</code></td>
                </tr>
                <tr>
                  <th scope="row">Primary Domain</th>
                  <td>{{ $tenant->primary_domain ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Template ID</th>
                  <td>{{ $tenant->template_id ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Seat Limit</th>
                  <td>{{ $tenant->user_seat_limit }}</td>
                </tr>
                <tr>
                  <th scope="row">Billing Email</th>
                  <td>{{ $tenant->billing_email ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Timezone</th>
                  <td>{{ $tenant->timezone ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Locale</th>
                  <td>{{ $tenant->locale ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Plan</th>
                  <td>{{ $tenant->plan ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Trial Ends At</th>
                  <td>{{ optional($tenant->trial_ends_at)->toDateTimeString() ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Created At</th>
                  <td>{{ optional($tenant->created_at)->toDateTimeString() ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Updated At</th>
                  <td>{{ optional($tenant->updated_at)->toDateTimeString() ?? '—' }}</td>
                </tr>
                <tr>
                  <th scope="row">Deleted At</th>
                  <td>{{ optional($tenant->deleted_at)->toDateTimeString() ?? '—' }}</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline-secondary">
              Back
            </a>

            <div class="d-inline-flex gap-2">
              @can('update', $tenant)
                @if ($tenant->status !== 'suspended')
                  <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="d-inline">
                    @csrf
                    <button
                      type="submit"
                      class="btn btn-warning"
                      onclick="return confirm('Suspend this tenant?')"
                    >Suspend</button>
                  </form>
                @else
                  <form method="POST" action="{{ route('admin.tenants.resume', $tenant) }}" class="d-inline">
                    @csrf
                    <button
                      type="submit"
                      class="btn btn-success"
                      onclick="return confirm('Resume this tenant?')"
                    >Resume</button>
                  </form>
                @endif
              @endcan

              @can('delete', $tenant)
                <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" class="d-inline">
                  @csrf
                  @method('DELETE')
                  <button
                    type="submit"
                    class="btn btn-danger"
                    onclick="return confirm('Delete (soft) this tenant?')"
                  >Delete</button>
                </form>
              @endcan
            </div>
          </div>
        </div>
      </div>

      {{-- Side card (optional notes or quick info) --}}
      <div class="col-12 col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <h2 class="h6 mb-0">Notes</h2>
          </div>
          <div class="card-body">
            <p class="text-muted mb-0">
              You can extend this panel with related counts (users, sites, themes) or quick links.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
