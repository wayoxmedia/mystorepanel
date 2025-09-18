@extends('admin.layouts.app')
@section('title', 'Manage Roles')
@php
/**
 * @var $allRoles
 * @var $currentSlug
 * @var $allowedSlugs
 */
@endphp
@section('content')
  <div class="container">
    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Manage Roles — {{ $user->name }} <span class="text-muted small">({{ $user->email }})</span></h1>
      <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back</a>
    </div>

    <form method="post" action="{{ route('admin.users.roles.update', $user) }}">
      @csrf

      <div class="card mb-3">
        <div class="card-header"><strong>Available roles</strong></div>
        <div class="card-body">
          <div class="row">
            @php
              $grouped = collect($allRoles)->groupBy('scope'); // platform / tenant
            @endphp

            @foreach($grouped as $scope => $roles)
              <div class="col-md-6">
                <h6 class="text-uppercase text-muted">{{ $scope }} roles</h6>
                @foreach($roles as $r)
                  @php
                    $checked = in_array($r->slug, $currentSlug, true);
                    $allowed = in_array($r->slug, $allowedSlugs, true);
                  @endphp
                  <div class="form-check mb-2">
                    <input class="form-check-input"
                           type="radio"
                           name="role"
                           id="role_{{ $r->slug }}"
                           value="{{ $r->slug }}"
                      {{ old('role', $currentSlug)[0] === $r->slug ? 'checked' : '' }}
                      {{ $allowed ? '' : 'disabled' }}>
                    <label class="form-check-label" for="role_{{ $r->slug }}">
                      {{ $r->name }} <span class="text-muted">({{ $r->slug }})</span>
                      @unless($allowed)
                        <span class="badge text-bg-light border ms-1">not allowed</span>
                      @endunless
                    </label>
                  </div>
                @endforeach
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary">Save roles</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
@endsection
