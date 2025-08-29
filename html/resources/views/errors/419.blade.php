@extends('admin.layout.app')
@section('title','Page expired')

@section('content')
  @php
    $user = auth()->user();
    $isSA = $user?->isPlatformSuperAdmin();
    $isManager = $user && method_exists($user,'hasAnyRole') && $user->hasAnyRole(['tenant_owner','tenant_admin']);
  @endphp

  <div class="card border-warning">
    <div class="card-body">
      <h1 class="h4 text-warning mb-2">419 — Page expired</h1>
      <p class="mb-3">
        Your session or the security token has expired. This can happen if the page was open for a long time or after logging out.
      </p>

      <div class="d-flex flex-wrap gap-2">
        @if(session()->has('impersonator_id'))
          <form method="post" action="{{ route('impersonate.stop') }}">
            @csrf
            <button class="btn btn-warning">Stop impersonating</button>
          </form>
        @endif

        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Go Back</a>
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reload</a>

        @auth
          <a href="{{ route('account.show') }}" class="btn btn-primary">My Account</a>
          @if($isSA || $isManager)
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">Go to Users</a>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">Log in again</a>
        @endauth
      </div>

      @auth
        @if(method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail())
          <div class="alert alert-info mt-3 mb-0">
            If you were verifying your email, please use the most recent link. You can resend it from My Account.
            <form method="post" action="{{ route('verification.send') }}" class="d-inline ms-2">
              @csrf
              <button class="btn btn-sm btn-outline-dark">Resend verification</button>
            </form>
          </div>
        @endif
      @endauth
    </div>
  </div>
@endsection
