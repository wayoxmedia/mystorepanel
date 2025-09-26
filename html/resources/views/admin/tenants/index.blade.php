{{--
  Tenant Index (Admin) — Bootstrap 5

  Purpose:
  - List tenants with simple search and pagination.
  - Provide quick actions: view, suspend/resume, delete (soft).

  Assumptions:
  - Route names: admin.tenants.index, admin.tenants.show, admin.tenants.suspend, admin.tenants.resume, admin.tenants.destroy
  - Bootstrap 5 CSS is loaded in your layout.
--}}

@extends('admin.layouts.app')

@section('title', 'Tenants')

@section('content')
  <div class="container py-4">

    {{-- Flash messages --}}
    @if (session('success'))
      <div class="alert alert-success" role="alert">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger" role="alert">
        <div class="fw-semibold mb-1">There were some problems:</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Header + Search --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Tenants</h1>

      <form method="GET" action="{{ route('admin.tenants.index') }}" class="d-flex gap-2">
        <input
          type="text"
          name="q"
          value="{{ request('q') }}"
          placeholder="Search by name, slug or domain…"
          class="form-control"
        />
        <button type="submit" class="btn btn-dark">
          Search
        </button>
      </form>
    </div>

    {{-- Table --}}
    <div class="table-responsive border rounded">
      <table class="table align-middle mb-0">
        <thead class="table-light">
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Name</th>
          <th scope="col">Slug</th>
          <th scope="col">Primary Domain</th>
          <th scope="col">Status</th>
          <th scope="col">Seats</th>
          <th scope="col" class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($tenants as $tenant)
          <tr>
            <td>{{ $tenant->id }}</td>
            <td>
              <a href="{{ route('admin.tenants.show', $tenant) }}" class="link-primary text-decoration-none">
                {{ $tenant->name }}
              </a>
            </td>
            <td>{{ $tenant->slug }}</td>
            <td>{{ $tenant->primary_domain ?? '—' }}</td>
            <td>
              @php
                $badgeClass = match($tenant->status) {
                  'active'    => 'bg-success',
                  'suspended' => 'bg-warning text-dark',
                  default     => 'bg-secondary'
                };
              @endphp
              <span class="badge {{ $badgeClass }}">
                {{ ucfirst($tenant->status) }}
              </span>
            </td>
            <td>{{ $tenant->user_seat_limit }}</td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                @can('view', $tenant)
                  <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-sm btn-primary">
                    View
                  </a>
                @endcan

                @can('update', $tenant)
                  @if ($tenant->status !== 'suspended')
                    <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="d-inline">
                      @csrf
                      <button
                        type="submit"
                        class="btn btn-sm btn-warning"
                        onclick="return confirm('Suspend this tenant?')"
                      >Suspend</button>
                    </form>
                  @else
                    <form method="POST" action="{{ route('admin.tenants.resume', $tenant) }}" class="d-inline">
                      @csrf
                      <button
                        type="submit"
                        class="btn btn-sm btn-success"
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
                      class="btn btn-sm btn-danger"
                      onclick="return confirm('Delete (soft) this tenant?')"
                    >Delete</button>
                  </form>
                @endcan
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              No tenants found.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-3">
      {{ $tenants->withQueryString()->links() }}
    </div>
  </div>
@endsection
