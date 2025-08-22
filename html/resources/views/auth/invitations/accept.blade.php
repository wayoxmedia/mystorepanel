@extends('admin.layouts.app')

@section('title', 'Accept Invitation')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card">
        <div class="card-header">
          <strong>Accept Invitation</strong>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="text-muted small">Invited email</div>
            <div class="fw-semibold">{{ $inv->email }}</div>
          </div>

          @if($tenant)
            <div class="mb-3">
              <div class="text-muted small">Tenant</div>
              <div class="fw-semibold">{{ $tenant->name }}</div>
            </div>
          @endif

          @if($role)
            <div class="mb-3">
              <div class="text-muted small">Role</div>
              <span class="badge text-bg-light border">{{ $role->name }}</span>
            </div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="post" action="{{ route('invitations.accept.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
              <label class="form-label">Full name</label>
              <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Password (min 10 chars)</label>
              <input type="password" name="password" class="form-control" minlength="10" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input type="password" name="password_confirmation" class="form-control" minlength="10" required>
            </div>

            <div class="d-grid gap-2">
              <button class="btn btn-primary" type="submit">Create account</button>
            </div>
          </form>

          <div class="mt-3 text-muted small">
            Token expires:
            @if($inv->expires_at)
              {{ $inv->expires_at->format('Y-m-d H:i') }}
            @else
              (no expiration set)
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
