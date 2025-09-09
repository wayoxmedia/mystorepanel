@extends('admin.layouts.app')
@section('title','Invitations')

@php
use Illuminate\Support\Carbon;
$tenant = $tenant ?? null;
$seats = $seats ?? (
  $tenant ? [
    'limit'     => $tenant->seatsLimit(),
    'used'      => $tenant->seatsUsed(),
    'available' => max(0, $tenant->seatsLimit() - $tenant->seatsUsed()),
  ] : null
);
$limitReached = $tenant && $seats && ($seats['available'] <= 0);
$actor  = auth()->user();
$isSA = $actor->isPlatformSuperAdmin();
$cooldown = (int) config('mystore.invitations.cooldown_minutes', 5);
@endphp
@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Invitations</h1>
@include('admin.partials.seats-cta', [
  'limitReached' => $limitReached,
  'primaryRoute' => route('admin.users.create'),
  'primaryLabel' => 'Invite / Create User',
  'buyMore'      => 'Buy More Users',
  'tenantId'     => $tenant->id ?? null,
  'isSA'         => $isSA,
])
  </div>

  {{-- Filtros --}}
  <form method="get" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
@if($isSA)
      <div class="col-sm-4 col-md-3">
        <label class="form-label mb-1">Tenant ID</label>
        <input type="number"
               name="tenant_id"
               value="{{ request('tenant_id') }}"
               class="form-control"
               placeholder="e.g. 42">
      </div>
@endif
      <div class="col-sm-4 col-md-3">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select">
@php $cur = request('status','pending'); @endphp
          <option value="pending"  {{ $cur==='pending' ? 'selected' : '' }}>Pending</option>
          <option value="accepted" {{ $cur==='accepted'? 'selected' : '' }}>Accepted</option>
          <option value="cancelled"{{ $cur==='cancelled'? 'selected' : '' }}>Cancelled</option>
          <option value="expired"  {{ $cur==='expired'? 'selected' : '' }}>Expired</option>
          <option value="all"      {{ $cur==='all' ? 'selected' : '' }}>All</option>
        </select>
      </div>

      <div class="col-sm-4 col-md-3">
        <div class="form-check mt-4 pt-1">
          <input class="form-check-input"
                 type="checkbox"
                 id="incl_exp"
                 name="includeExpired"
                 value="1"
            {{ request()->boolean('include_expired') ? 'checked' : '' }}>
          <label class="form-check-label" for="includeExpired">
            Include expired (when pending)</label>
        </div>
      </div>

      <div class="col-sm-4 col-md-3">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Email</th>
@if($isSA)  <th>Tenant</th> @endif
            <th>Role</th>
            <th>Status</th>
            <th>Expires</th>
            <th>Last sent</th>
            <th class="text-center">Sends</th>
            <th class="text-end" style="width:28%">Actions</th>
          </tr>
        </thead>
        <tbody>
@forelse($invitations as $inv)
@php
$status = $inv->status;
$badge  = match($status) {
  'pending'   => 'warning',
  'accepted'  => 'success',
  'cancelled' => 'secondary',
  'expired'   => 'dark',
  default     => 'light'
};

// Cooldown calc
$lastSent     = $inv->last_sent_at ? Carbon::parse($inv->last_sent_at) : null;
$cooldownTill = ($cooldown > 0 && $lastSent) ? $lastSent->copy()->addMinutes($cooldown) : null;
$inCooldown   = $cooldownTill && now()->lt($cooldownTill);
$minsLeft     = $inCooldown ? (int) ceil(now()->diffInSeconds($cooldownTill)/60) : 0;

$expiresAt = $inv->expires_at ? Carbon::parse($inv->expires_at) : null;
@endphp
          <tr>
            <td class="fw-semibold">{{ $inv->email }}</td>
@if($isSA)
            <td>{{ optional($inv->tenant)->name ?? '—' }}</td>
@endif
            <td>{{ optional($inv->role)->name ?? '—' }}</td>

            <td><span class="badge text-bg-{{ $badge }}">{{ $status }}</span></td>

            <td>
@if($expiresAt)
              <span title="{{ $expiresAt->toDateTimeString() }}">{{ $expiresAt->diffForHumans() }}</span>
@else — @endif
            </td>

            <td>
@if($lastSent)
              <span title="{{ $lastSent->toDateTimeString() }}">{{ $lastSent->diffForHumans() }}</span>
@else — @endif
            </td>

            <td class="text-center">{{ (int)($inv->send_count ?? 0) }}</td>

            <td class="text-end">
              <div class="d-flex flex-wrap justify-content-end gap-2">
                {{-- Resend --}}
                <form method="post" action="{{ route('admin.invitations.resend', $inv) }}"
                      onsubmit="return confirm('Resend invitation to {{ $inv->email }}?');">
@csrf
                  <button class="btn btn-sm btn-outline-primary"
@if($status === 'accepted' || $inCooldown) disabled @endif
@if($status === 'accepted') title="Already accepted" @elseif($inCooldown) title="Try again in {{ $minsLeft }} min" @endif
                  >
                    Resend
                  </button>
                </form>

                {{-- Cancel (solo si pending) --}}
@if($status === 'pending')
                <form method="post" action="{{ route('admin.invitations.cancel', $inv) }}"
                      onsubmit="return confirm('Cancel this invitation?');">
@csrf
                  <button class="btn btn-sm btn-outline-secondary">Cancel</button>
                </form>
@endif
              </div>
            </td>
          </tr>
@empty
          <tr>
            <td colspan="{{ $isSA ? 8 : 7 }}" class="text-center text-muted py-4">No invitations found.</td>
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
