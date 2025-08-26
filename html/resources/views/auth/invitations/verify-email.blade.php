@extends('admin.layouts.app')
@section('title', 'Verify Email')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card">
        <div class="card-header"><strong>Verify your email</strong></div>
        <div class="card-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
          @endif

          <p class="mb-3">
            We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
            Please check your inbox (and spam).
          </p>

          <form method="post" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary">Resend verification email</button>
          </form>

          <hr class="my-4">

          <p class="text-muted small mb-2">Wrong account?</p>
          <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm">Logout</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
