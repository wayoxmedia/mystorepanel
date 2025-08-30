@extends('admin.layout.app')
@section('title','Buy More Users')

@section('content')
@php
  $isSA = auth()->user()?->isPlatformSuperAdmin();
  $hasTenant = !empty($tenant);
  $limit = $seats['limit'] ?? null;
  $used  = $seats['used']  ?? null;
  $avail = $seats['available'] ?? null;
  $minDesired = $hasTenant && $used !== null ? max($used + 1, 1) : 1;
@endphp

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Buy More Users</h1>
    <a href="{{ route('admin.tenants.seats.index') }}" class="btn btn-outline-secondary">Back to Seats</a>
  </div>

@if($isSA && !$hasTenant)
    {{-- SA sin tenant: pedir tenant_id por query --}}
    <div class="alert alert-info">
      <div class="fw-semibold mb-1">Select a tenant to continue</div>
      <div class="small">As a Platform Super Admin, choose a tenant to request a seat increase for.</div>
    </div>

    <form method="get" action="{{ route('admin.seats.upgrade.show') }}" class="card">
      <div class="card-body row g-3 align-items-end">
        <div class="col-sm-6 col-md-4">
          <label class="form-label">Tenant ID</label>
          <input type="number" name="tenant_id" class="form-control" placeholder="e.g. 42" required>
          <div class="form-text">
            You can find IDs in the <a href="{{ route('admin.tenants.seats.index') }}">Seats list</a>.
          </div>
        </div>
        <div class="col-sm-6 col-md-3">
          <button class="btn btn-primary">Open tenant</button>
        </div>
      </div>
    </form>
  @else
    {{-- Info del tenant y estado actual de seats --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div class="mb-2">
          <div class="fw-semibold">{{ $tenant->name ?? '—' }}</div>
@if(isset($tenant->slug))
            <div class="text-muted small">{{ $tenant->slug }}</div>
@endif
        </div>

@if($limit !== null)
        <div class="d-flex gap-3 text-center">
          <div>
            <div class="small text-muted">Used</div>
            <div class="fs-5 fw-semibold">{{ $used }}</div>
          </div>
          <div>
            <div class="small text-muted">Limit</div>
            <div class="fs-5 fw-semibold">{{ $limit }}</div>
          </div>
          <div>
            <div class="small text-muted">Available</div>
            <div class="fs-5 fw-semibold">
@if($avail === 0)
                <span class="badge text-bg-warning">0</span>
@else
                {{ $avail }}
@endif
            </div>
          </div>
        </div>
@endif
      </div>
    </div>
  </div>

    {{-- Formulario de solicitud (placeholder; solo auditoría) --}}
    <form method="post" action="{{ route('admin.seats.upgrade.request') }}" class="card">
      @csrf
      @if($isSA && $hasTenant)
        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
      @endif

      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Desired user limit</label>
          <input
            type="number"
            name="desired_limit"
            class="form-control @if($errors->has('desired_limit')) is-invalid @endif"
            value="{{ old('desired_limit', $limit !== null ? max($limit + 1, $minDesired) : $minDesired) }}"
            min="{{ $minDesired }}"
            step="1"
            required
          >
          @if($errors->has('desired_limit'))
            <div class="invalid-feedback">{{ $errors->first('desired_limit') }}</div>
          @else
            <div class="form-text">
              Minimum: {{ $minDesired }} (current used: {{ $used ?? 'n/a' }}).
            </div>
          @endif
        </div>

        <div class="mb-3">
          <label class="form-label">Notes (optional)</label>
          <textarea
            name="note"
            rows="3"
            class="form-control @if($errors->has('note')) is-invalid @endif"
            placeholder="Tell us anything we should know (e.g., expected growth, urgency)"
          >{{ old('note') }}</textarea>
          @if($errors->has('note'))
            <div class="invalid-feedback">{{ $errors->first('note') }}</div>
          @endif
        </div>

        <div class="d-flex justify-content-end">
          <button class="btn btn-primary">Submit request</button>
        </div>
      </div>

      <div class="card-footer text-muted small">
        This request won’t change your limit automatically. Our team will review it and get back to you.
      </div>
    </form>
  @endif
@endsection
