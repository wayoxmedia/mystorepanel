@extends('layouts.app')

@section('content')
  <div class="container">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Users</h1>
      <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Create / Invite User</a>
    </div>

    <form method="get" class="row g-2 mb-3">
      <div class="col-auto">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name or email">
      </div>
      @if($tenants->count() > 1)
        <div class="col-auto">
          <select name="tenant_id" class="form-select">
            <option value="">All tenants</option>
            @foreach($tenants as $t)
              <option value="{{ $t->id }}" @selected(request('tenant_id') == $t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="col-auto">
        <select name="role" class="form-select">
          <option value="">All roles</option>
          @foreach($roles as $r)
            <option value="{{ $r->slug }}" @selected(request('role') == $r->slug)>{{ $r->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto">
        <select name="status" class="form-select">
          <option value="">All statuses</option>
          <option value="active" @selected(request('status')=='active')>active</option>
          <option value="pending_invite" @selected(request('status')=='pending_invite')>pending_invite</option>
          <option value="suspended" @selected(request('status')=='suspended')>suspended</option>
          <option value="locked" @selected(request('status')=='locked')>locked</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-secondary">Filter</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
        <tr>
          <th>ID</th>
          <th>Name / Email</th>
          <th>Tenant</th>
          <th>Roles</th>
          <th>Status</th>
          <th>Created</th>
        </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
          <tr>
            <td>{{ $u->id }}</td>
            <td>
              <div class="fw-semibold">{{ $u->name }}</div>
              <div class="text-muted small">{{ $u->email }}</div>
            </td>
            <td>{{ $u->tenant?->name ?? '—' }}</td>
            <td>
              @foreach($u->roles as $r)
                <span class="badge text-bg-light border">{{ $r->name }}</span>
              @endforeach
            </td>
            <td><span class="badge text-bg-{{ $u->status === 'active' ? 'success' : 'secondary' }}">{{ $u->status }}</span></td>
            <td class="text-muted small">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted">No users found.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{ $users->links() }}
  </div>
@endsection
