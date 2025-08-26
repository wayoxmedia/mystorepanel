@extends('admin.layouts.app')

@section('title','My Account')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><strong>My Account</strong></div>
        <div class="card-body">
          <div class="mb-2"><strong>Name:</strong> {{ $user->name }}</div>
          <div class="mb-2"><strong>Email:</strong> {{ $user->email }}</div>
          @if($user->tenant)
            <div class="mb-2"><strong>Tenant:</strong> {{ $user->tenant->name }}</div>
          @endif
          <div class="text-muted small">Role(s): {{ $user->roles->pluck('name')->implode(', ') }}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Change Password</strong></div>
        <div class="card-body">
          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif
          <form method="post" action="{{ route('account.password.update') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">Current password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">New password (min 10)</label>
              <input type="password" name="password" class="form-control" minlength="10" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm new password</label>
              <input type="password" name="password_confirmation" class="form-control" minlength="10" required>
            </div>
            <button class="btn btn-primary">Update password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
