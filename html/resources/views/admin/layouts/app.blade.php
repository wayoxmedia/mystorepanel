<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'My Store App Admin')</title>

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Place for page-specific head injections -->
  @stack('head')

  <style>
    /* Small helpers; keep it minimal */
    body { padding-top: 4.5rem; }
    .navbar-brand { font-weight: 600; }
  </style>
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="{{ route('admin.users.index') }}">My Store App Admin</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topNav">
      @php($isSA = auth()->user()?->isPlatformSuperAdmin())
      @php($isToOrTa = auth()->user()?->hasAnyRole(['tenant_owner','tenant_admin']))

      <ul class="navbar-nav me-auto">

        {{-- Users --}}
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
             href="{{ route('admin.users.index') }}">
            Users
          </a>
        </li>

        {{-- Tenants (dropdown) --}}
        @php($tenantsActive = request()->routeIs('admin.tenants.*'))
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle {{ $tenantsActive ? 'active' : '' }}" href="#" id="navTenantsDropdown"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Tenants
          </a>
          <ul class="dropdown-menu" aria-labelledby="navTenantsDropdown">
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.tenants.index') && !request('status') ? 'active' : '' }}"
                 href="{{ route('admin.tenants.index') }}">
                All
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.tenants.index') && request('status') === 'active' ? 'active' : '' }}"
                 href="{{ route('admin.tenants.index', ['status' => 'active']) }}">
                Active
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.tenants.index') && request('status') === 'suspended' ? 'active' : '' }}"
                 href="{{ route('admin.tenants.index', ['status' => 'suspended']) }}">
                Suspended
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            @can('create', \App\Models\Tenant::class)
              <li>
                <a class="dropdown-item {{ request()->routeIs('admin.tenants.create') ? 'active' : '' }}"
                   href="{{ route('admin.tenants.create') }}">
                  Create
                </a>
              </li>
            @endcan
            {{-- Future: Reports, Settings, etc. --}}
            <li><hr class="dropdown-divider"></li>
            <li class="dropdown-header">Shortcuts</li>
            <li>
              <a class="dropdown-item"
                 href="{{ route('admin.tenants.index', ['q' => 'example']) }}">
                Search “example”
              </a>
            </li>
          </ul>
        </li>
        {{-- Admin (dropdown) --}}
        @php($adminActive =
            request()->routeIs('admin.deliverability.*') ||
            request()->routeIs('admin.workers.*') ||
            request()->routeIs('admin.authz.*') ||
            request()->routeIs('admin.db.*') ||
            request()->routeIs('admin.branding.*') ||
            request()->routeIs('admin.ops.*') ||
            request()->routeIs('admin.audit.*') ||
            request()->routeIs('admin.cta.*')
        )
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle {{ $adminActive ? 'active' : '' }}" href="#" id="navAdminDropdown"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Admin
          </a>
          <ul class="dropdown-menu" aria-labelledby="navAdminDropdown">

            {{-- Deliverability --}}
            <li class="dropdown-header">Deliverability</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.deliverability.dmarc') ? 'active' : '' }}"
                 href="#">
                DMARC Policies
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.deliverability.staging') ? 'active' : '' }}"
                 href="#">
                Staging Safelist / Suppression
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- Workers & Queues --}}
            <li class="dropdown-header">Workers & Queues</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.workers.horizon') ? 'active' : '' }}"
                 href="#">
                Horizon Dashboard
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.workers.health') ? 'active' : '' }}"
                 href="#">
                Health Checks & Alerts
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.workers.metrics') ? 'active' : '' }}"
                 href="#">
                Job Metrics
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- Authorization --}}
            <li class="dropdown-header">Authorization</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.authz.audit') ? 'active' : '' }}"
                 href="#">
                Controllers Coverage
              </a>
            </li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.authz.messages') ? 'active' : '' }}"
                 href="#">
                UX & Error Messages
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- DB Hardening --}}
            <li class="dropdown-header">Database</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.db.indexes') ? 'active' : '' }}"
                 href="#">
                Indexes & Integrity
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- Branding (emails) --}}
            <li class="dropdown-header">Branding</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.branding.templates') ? 'active' : '' }}"
                 href="#">
                Email Templates & i18n
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- Ops / Feature Flags --}}
            <li class="dropdown-header">Operability</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.ops.flags') ? 'active' : '' }}"
                 href="#">
                Config & Feature Flags
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- Audit --}}
            <li class="dropdown-header">Audit</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.audit.events') ? 'active' : '' }}"
                 href="#">
                Audit Events
              </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            {{-- CTA: Buy More Users --}}
            <li class="dropdown-header">CTA</li>
            <li>
              <a class="dropdown-item {{ request()->routeIs('admin.cta.buy-more-users') ? 'active' : '' }}"
                 href="#">
                Buy More Users
              </a>
            </li>
          </ul>
        </li>

        {{-- Invitations (tenant_owner / tenant_admin / SA) --}}
        @if($isSA || $isToOrTa)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.invitations.*') ? 'active' : '' }}"
               href="{{ route('admin.invitations.index') }}">
              Invitations
            </a>
          </li>
        @endif

        {{-- Seats (SA only) --}}
        @if($isSA)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.tenants.seats.*') ? 'active' : '' }}"
               href="{{ route('admin.tenants.seats.index') }}">
              Seats
            </a>
          </li>
        @endif

        {{-- (Optional) more admin links here --}}
      </ul>

      <ul class="navbar-nav ms-auto">
        {{-- My Account --}}
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('account.show') ? 'active' : '' }}"
             href="{{ route('account.show') }}">
            My Account
          </a>
        </li>

        {{-- User name + Platform badge (if SA) --}}
        @if(auth()->user()?->isPlatformSuperAdmin())
          <li class="nav-item">
        <span class="nav-link disabled">
          {{ auth()->user()->name }} <span class="badge text-bg-secondary ms-1">Platform</span>
        </span>
          </li>
        @else
          <li class="nav-item">
            <span class="nav-link disabled">{{ auth()->user()->name ?? 'New User' }}</span>
          </li>
        @endif

        {{-- Logout --}}
        <li class="nav-item">
          <form method="post" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button class="btn btn-link nav-link">Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

@if (session()->has('impersonator_id'))
  <div class="bg-warning py-2 mb-3">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="small">
        <strong>Impersonating:</strong> {{ auth()->user()->email }}
        <span class="text-muted ms-2">as requested by {{ session('impersonator_email') }}</span>
      </div>
      <form method="post" action="{{ route('impersonate.stop') }}">
        @csrf
        <button class="btn btn-sm btn-dark">Stop impersonating</button>
      </form>
    </div>
  </div>
@endif

<!-- Main content -->
<main class="container">
  @include('admin.partials.flash')
  @yield('content')
</main>

<!-- jQuery (optional but handy for admin tooling) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="{{ asset('admin/js/main.js') }}"></script>

<!-- Place for page-specific scripts -->
@stack('scripts')
</body>
</html>
