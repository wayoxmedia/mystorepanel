@extends('admin.layouts.app')
@section('title','Reset Password')

@section('content')
  <div class="row justify-content-center"><div class="col-md-6">
      <div class="card">
        <div class="card-header"><strong>Reset Password</strong></div>
        <div class="card-body">
          @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
          @endif
          <form method="post" action="{{ route('password.update') }}">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <div class="mb-3">
              <label class="form-label">New password (min 10)</label>
              <input type="password" name="password" class="form-control" minlength="10" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input type="password" name="password_confirmation" class="form-control" minlength="10" required>
            </div>
            <button class="btn btn-primary">Update password</button>
          </form>
        </div>
      </div>
    </div></div>
@endsection
