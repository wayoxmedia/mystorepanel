@extends('admin.layouts.app')

@section('title', 'Login')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card">
        <div class="card-header"><strong>Login</strong></div>
        <div class="card-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="post" action="{{ route('login.attempt') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
              <label class="form-check-label" for="remember">
                Remember me
              </label>
            </div>
            <div class="d-grid gap-2">
              <button class="btn btn-primary">Login</button>
            </div>
          </form>
        </div>
      </div>

      <div class="text-center mt-3">
        <a class="small text-muted" href="{{ route('password.request') }}">Forgot your password?</a>
      </div>
    </div>
  </div>
@endsection
