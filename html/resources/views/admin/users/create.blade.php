{{-- resources/views/admin/users/create.blade.php --}}
@extends('layouts.app')

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
        <div class="col-md-6">
          <label class="form-label">Full name</label>
          <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
        </div>

        @if($tenants->count() >= 1)
          <div class="col-md-6">
            <label class="form-label">Tenant</label>
            <select name="tenant_id" class="form-select">
              @if(auth()->user()->isPlatformSuperAdmin())
                <option value="">(Platform staff)</option>
              @endif
              @foreach($tenants as $t)
                <option value="{{ $t->id }}" @selected(old('tenant_id')==$t->id)>{{ $t->name }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-md-6">
          <label class="form-label">Role</label>
          <select name="role_slug" class="form-select" required>
            @foreach($roles as $r)
              <option value="{{ $r->slug }}" @selected(old('role_slug')==$r->slug)>{{ $r->name }}</option>
            @endforeach
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
