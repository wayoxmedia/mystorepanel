@php use App\Models\User; @endphp
@extends('admin.layouts.app')

@php
  /** @var User $actor */
  $isSA = $actor->isPlatformSuperAdmin();
@endphp

@section('content')
  <div class="container">
    <h1 class="h4 mb-3">Create / Invite User</h1>

@if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
@endif
    <form method="post" action="{{ route('admin.users.store') }}">
@csrf
      <div class="mb-3">
        <label class="form-label">Mode</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" id="modeInvite" value="invite" checked>
          <label class="form-check-label" for="modeInvite">Invite via email</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" id="modeCreate" value="create">
          <label class="form-check-label" for="modeCreate">Create now (set password)</label>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-6 input-box">
          <input type="text"
                 id="name"
                 name="name"
                 value="{{ old('name') }}"
                 class="form-control"
                 placeholder=" "
                 required>
          <label for="name" class="form-label dynamic">Full name</label>
        </div>
        <div class="col-md-6 input-box">
          <input type="email"
                 id="email"
                 name="email"
                 value="{{ old('email') }}"
                 class="form-control"
                 placeholder=" "
                 required>
          <label for="email" class="form-label dynamic">Email</label>
        </div>

@if($tenants->count() >= 1 && $isSA)
          <div class="col-md-6">
            <label class="form-label">Tenant</label>
            <select name="tenant_id" class="form-select">
@if(auth()->user()->isPlatformSuperAdmin())
              <option value="">(Platform staff)</option>
@endif
@foreach($tenants as $t)
              <option value="{{ $t->id }}" @selected(old('tenant_id')==$t->id)>
                {{ $t->name }}
              </option>
@endforeach
            </select>
          </div>
@endif

        <div class="col-md-6">
          <label class="form-label">Role</label>
          <select name="role_slug" class="form-select" required>
@forelse($roles as $r)
            <option value="{{ $r->slug }}" @selected(old('role_slug')==$r->slug)>
              {{$r->name}}
            </option>
@empty
            <option value="" disabled>(no roles available)</option>
@endforelse
          </select>
        </div>

        <div class="col-md-6 d-none" id="passwordWrap">
          <label class="form-label">Initial password (min 10 chars)</label>
          <input type="password" name="password" class="form-control" minlength="10">
          <div class="form-text">The user should change it after first login.</div>
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const modeInvite = document.getElementById('modeInvite');
      const modeCreate = document.getElementById('modeCreate');
      const passwordWrap = document.getElementById('passwordWrap');

      function togglePassword() {
        const create = modeCreate.checked;
        passwordWrap.classList.toggle('d-none', !create);
      }
      modeInvite.addEventListener('change', togglePassword);
      modeCreate.addEventListener('change', togglePassword);
      togglePassword();
    });
  </script>
@endpush

<style>
  .input-box {
    position: relative;
    display: inline-block;
  }
  .dynamic {
    position: absolute;
    left: 12px;
    top: 7px;
    color: gray;
    background: white; /* hides border behind label */
    padding: 0 4px; /* adds space around text */
    transition: 0.3s ease;
    pointer-events: none;
  }
  input[type=text], input[type=email]:focus {
    border-color: #0051ff;
  }
  /* Floating effect on focus or when input has text */
  input[type="text"]:focus + label.dynamic,
  input[type="text"]:not(:placeholder-shown) + label.dynamic,
  input[type="email"]:focus + label.dynamic,
  input[type="email"]:not(:placeholder-shown) + label.dynamic {
    transform: translateY(-20px);
    font-size: 12px;
    color: #0051ff;
  }
</style>
