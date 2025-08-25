@extends('admin.layouts.app')
@section('title','Invitations')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Invitations</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Invite / Create User</a>
  </div>

  {{-- Filtros --}}
  <form method="get" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-sm-4 col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          @foreach(['pending','accepted','cancelled','expired','all'] as $opt)
            <option value="{{ $opt }}" @selected(($status ?? 'pending') === $opt)>{{ ucfirst($opt) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-sm-5 col-md-4">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" id="incl_exp" name="include_expired" value="1" @checked(request('include_expired'))>
          <label class="form-check-label" for="incl_exp">Include expired when status is pending</label>
        </div>
      </div>

      <div class="col-sm-3 text-end">
        <button class="btn btn-outline-secondary mt-2 mt-sm-0">Apply</button>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
        <tr>
          <th>Email</th>
          <th>Role</th>
          <th>Tenant</th>
          <th>Status</th>
          <th>Expires</th>
          <th>Invited by</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($invitations as $inv)
          @php
            $expired = $inv->expires_at && $inv->expires_at->isPast();
          @endphp
          <tr>
            <td class="fw-semibold">{{ $inv->email }}</td>
            <td>
              {{ $inv->role?->name ?? '—' }}
              @if($inv->role?->slug)
                <div class="text-muted small">{{ $inv->role->slug }}</div>
              @endif
            </td>
            <td>{{ $inv->tenant?->name ?? '—' }}</td>
            <td>
              @if($inv->status === 'pending' && $expired)
                <span class="badge text-bg-warning">expired</span>
              @else
                <span class="badge text-bg-light border">{{ $inv->status }}</span>
              @endif
            </td>
            <td>
              @if($inv->expires_at)
                <div>{{ $inv->expires_at->format('Y-m-d H:i') }}</div>
                <div class="text-muted small">{{ $inv->expires_at->diffForHumans() }}</div>
              @else
                —
              @endif
            </td>
            <td>
              @if($inv->invited_by)
                <span class="text-muted">#{{ $inv->invited_by }}</span>
              @else
                —
              @endif
            </td>
            <td class="text-muted">{{ $inv->created_at?->format('Y-m-d H:i') }}</td>
            <td class="text-end">
              {{-- Resend: permitido salvo que esté aceptada --}}
              @if($inv->status !== 'accepted')
                <form method="post" action="{{ route('admin.invitations.resend', $inv) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="return confirm('Resend invitation to {{ $inv->email }}?');">
                    Resend
                  </button>
                </form>
              @endif

              {{-- Cancel: solo cuando está pendiente (aunque esté vencida) --}}
              @if($inv->status === 'pending')
                <form method="post" action="{{ route('admin.invitations.cancel', $inv) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger"
                          onclick="return confirm('Cancel invitation for {{ $inv->email }}?');">
                    Cancel
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-4">No invitations found.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $invitations->links() }}
    </div>
  </div>
@endsection
