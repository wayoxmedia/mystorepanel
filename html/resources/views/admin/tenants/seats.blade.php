@extends('admin.layouts.app')
@section('title','Tenant Seats')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Tenant Seats</h1>
    <form method="get" class="d-flex gap-2" action="{{ route('admin.tenants.seats.index') }}">
      <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search by name or slug">
      <button class="btn btn-outline-secondary">Search</button>
    </form>
  </div>

  @php $isSA = auth()->user()?->isPlatformSuperAdmin(); @endphp

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
        <tr>
          <th style="width:28%">Tenant</th>
          <th>Slug</th>
          <th class="text-center">Used / Limit</th>
          <th class="text-center">Available</th>
          <th class="text-end" style="width:28%">Update Limit</th>
        </tr>
        </thead>
        <tbody>
        @forelse($tenants as $t)
          @php
            $limit = $stats[$t->id]['limit'] ?? 0;
            $used  = $stats[$t->id]['used'] ?? 0;
            $avail = max(0, $limit - $used);
            $errKey = 'user_seat_limit_'.$t->id; // para el error "no puede ser menor que usados"
          @endphp
          <tr>
            <td class="fw-semibold">{{ $t->name }}</td>
            <td class="text-muted">{{ $t->slug }}</td>
            <td class="text-center">
              <span class="fw-semibold">{{ $used }}</span> / {{ $limit }}
            </td>
            <td class="text-center">
              @if($avail === 0)
                <span class="badge text-bg-warning">0</span>
              @else
                {{ $avail }}
              @endif
            </td>
            <td class="text-end">
              @if($isSA)
                <form method="post" action="{{ route('admin.tenants.seats.update', $t) }}" class="d-inline-flex align-items-center justify-content-end gap-2">
                  @csrf
                  <div class="text-start">
                    <input type="number"
                           name="user_seat_limit"
                           class="form-control @error('user_seat_limit') is-invalid @enderror @if($errors->has($errKey)) is-invalid @endif"
                           value="{{ old('user_seat_limit', $limit) }}"
                           min="{{ max(1, $used) }}"
                           step="1"
                           style="width: 140px;">
                    @error('user_seat_limit')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if($errors->has($errKey))
                      <div class="invalid-feedback d-block">{{ $errors->first($errKey) }}</div>
                    @endif
                    <div class="form-text">Min: {{ max(1, $used) }}</div>
                  </div>
                  <button class="btn btn-primary">Save</button>
                </form>
              @else
                <span class="text-muted small">Only platform super admins can update.</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">No tenants found.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $tenants->links() }}
    </div>
  </div>
@endsection
