@php use App\Models\User; @endphp
@extends('admin.layouts.app')
@section('title','Access denied')

@section('content')
  @php
    /** @var User|null $user */
    $user = auth()->user();
    $isSA = $user?->isPlatformSuperAdmin();
    $isManager = $user && $user->hasAnyRole(['tenant_owner','tenant_admin']);
  @endphp

  <div class="card border-danger">
    <div class="card-body">
      <h1 class="h4 text-danger mb-2">Access denied</h1>
      <p class="mb-3">
        You don’t have permission to access this page or perform this action.
      </p>

      <div class="d-flex flex-wrap gap-2">
        @if(session()->has('impersonator_id'))
          <form method="post" action="{{ route('impersonate.stop') }}">
            @csrf
            <button class="btn btn-warning">Stop impersonating</button>
          </form>
        @endif

        <a href="{{ route('account.show') }}"
           class="btn btn-outline-secondary">My Account</a>

        @if($isSA || $isManager)
          <a href="{{ route('admin.users.index') }}"
             class="btn btn-primary">Go to Users</a>
        @endif

        <a href="{{ url()->previous() }}"
           class="btn btn-link">Back</a>
      </div>

      @if($user && !$user->hasVerifiedEmail())
        <div class="alert alert-warning mt-3 mb-0">
          <strong>Note:</strong> your email isn’t verified. Some areas may be restricted.
          <form method="post" action="{{ route('verification.send') }}" class="d-inline ms-2">
            @csrf
            <button class="btn btn-sm btn-outline-dark">Resend verification</button>
          </form>
        </div>
      @endif
    </div>
  </div>
@endsection
