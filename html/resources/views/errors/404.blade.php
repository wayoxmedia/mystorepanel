@extends('admin.layouts.app')
@section('title','Page not found')

@section('content')
  @php
    $user = auth()->user();
    $isSA = $user?->isPlatformSuperAdmin();
    $isManager = $user && method_exists($user,'hasAnyRole') && $user->hasAnyRole(['tenant_owner','tenant_admin']);
  @endphp

  <div class="card border-secondary">
    <div class="card-body">
      <h1 class="h4 mb-2">404 — Page not found</h1>
      <p class="mb-3">The page you’re looking for doesn’t exist, was moved, or you don’t have access to it.</p>

      <div class="d-flex flex-wrap gap-2">
        @if(session()->has('impersonator_id'))
          <form method="post" action="{{ route('impersonate.stop') }}">
            @csrf
            <button class="btn btn-warning">Stop impersonating</button>
          </form>
        @endif

        @auth
          <a href="{{ route('account.show') }}" class="btn btn-outline-secondary">My Account</a>
          @if($isSA || $isManager)
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Go to Users</a>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Go to Login</a>
        @endauth

        <a href="{{ url()->previous() }}" class="btn btn-link">Back</a>
        <a href="{{ url('/') }}" class="btn btn-link">Home</a>
      </div>
    </div>
  </div>
@endsection
