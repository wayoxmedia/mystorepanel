@extends('admin.layouts.app')

@section('content')
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Users</h1>
      @php(xdebug_break())
      @php($disabled = $tenant && $seats && $seats['available'] <= 0)
      <button class="btn btn-primary" {{ $disabled ? 'disabled' : '' }}>Invite User</button>
      {{-- or --}}
      <button class="btn btn-success" {{ $disabled ? 'disabled' : '' }}>Create User</button>
      @if($disabled)
        <div class="small text-muted mt-1">No seats available. Delete a user or increase the limit.</div>
      @endif
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
          <option value="active" @selected(request('status')=='active')>Active</option>
          <option value="pending_invite" @selected(request('status')=='pending_invite')>Pending Invite</option>
          <option value="suspended" @selected(request('status')=='suspended')>Suspended</option>
          <option value="locked" @selected(request('status')=='locked')>Locked</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-secondary">Filter</button>
      </div>
    </form>

    @if($tenant && $seats)
      <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
          <strong>Seats:</strong> {{ $seats['used'] }} / {{ $seats['limit'] }}
          <span class="text-muted ms-2">Available: {{ $seats['available'] }}</span>
        </div>
        @if($seats['available'] <= 0)
          <span class="badge text-bg-warning">Limit reached</span>
        @endif
      </div>
    @endif

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
              @can('impersonate-user', $u)
                <form method="post"
                      action="{{ route('admin.impersonate.start', $u) }}"
                      class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-primary mt-1"
                          onclick="return confirm('Impersonate {{ $u->email }}?');">
                    Impersonate
                  </button>
                </form>
              @endcan
            </td>
            <td>{{ $u->tenant?->name ?? '—' }}</td>
            <td>
              @foreach($u->roles as $r)
                <span class="badge text-bg-light border">{{ $r->name }}</span>
                <div class="mt-1">
                  <a class="btn btn-sm btn-outline-secondary"
                     href="{{ route('admin.users.roles.edit', $u) }}">Manage roles</a>
                </div>
                {{-- State actions, only if you have permissions and you are NOT the same user --}}
                @if(auth()->id() !== $u->id)
                  <div class="mt-2 d-flex flex-wrap gap-1">
                    {{-- Activate --}}
                    @if($u->status !== 'active')
                      <form method="post"
                            action="{{ route('admin.users.status.update', $u) }}"
                            onsubmit="return confirm('Activate {{ $u->email }}?');">
                        @csrf
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn-sm btn-success">Activate</button>
                      </form>
                    @endif

                    {{-- Suspend --}}
                    @if($u->status !== 'suspended')
                      <form method="post"
                            action="{{ route('admin.users.status.update', $u) }}"
                            onsubmit="return confirm('Suspend {{ $u->email }}?');">
                        @csrf
                        <input type="hidden" name="status" value="suspended">
                        <button class="btn btn-sm btn-outline-warning">Suspend</button>
                      </form>
                    @endif

                    {{-- Lock --}}
                    @if($u->status !== 'locked')
                      <form method="post" action="{{ route('admin.users.status.update', $u) }}"
                            onsubmit="return confirm('Lock {{ $u->email }}?');">
                        @csrf
                        <input type="hidden" name="status" value="locked">
                        <button class="btn btn-sm btn-outline-danger">Lock</button>
                      </form>
                    @endif

                    @if(auth()->id() !== $u->id)
                      <form method="post" action="{{ route('admin.users.destroy', $u) }}"
                            onsubmit="return confirm('Delete {{ $u->email }} permanently? This cannot be undone.');"
                            class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    @endif

                  </div>
                @endif

                {{-- (Optional) Reason for Audit --}}
                <input type="hidden" name="reason" value="Billing overdue #12345">
              @endforeach
            </td>
            <td>
              <span class="badge text-bg-{{ $u->status === 'active' ? 'success' : 'secondary' }}">{{ $u->status }}
              </span>
            </td>
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
