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
      <ul class="navbar-nav me-auto">
        <!-- Add more admin links here as needed -->
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
            Users
          </a>
        </li>
        {{-- Invitations (solo para managers de tenant o SA) --}}
        @php($isSA = auth()->user()?->isPlatformSuperAdmin())
        @php($isToOrTa = auth()->user()?->hasAnyRole(['tenant_owner','tenant_admin']))
        @if($isSA || $isToOrTa)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.invitations.*') ? 'active' : '' }}"
               href="{{ route('admin.invitations.index') }}">
              Invitations
            </a>
          </li>
        @endif
        @if($isSA)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.tenants.seats.*') ? 'active' : '' }}"
               href="{{ route('admin.tenants.seats.index') }}">
              Seats
            </a>
          </li>
        @endif
      </ul>

      <ul class="navbar-nav ms-auto">
        @auth
          <li class="nav-item d-flex align-items-center me-2 text-white-50 small">
            @php($u = auth()->user())
            <span class="me-2">{{ $u->name }}</span>
            <span class="badge bg-secondary">{{ $u->tenant?->name ?? 'Platform' }}</span>
          </li>
          @if (Route::has('logout'))
            <li class="nav-item">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-light">Logout</button>
              </form>
            </li>
          @endif
        @else
          @if (Route::has('login'))
            <li class="nav-item">
              <a class="btn btn-sm btn-outline-light" href="{{ route('login') }}">Login</a>
            </li>
          @endif
        @endauth
      </ul>
    </div>
  </div>
</nav>

@if (session()->has('impersonator_id'))
  <div class="bg-warning py-2">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="small">
        <strong>Impersonating:</strong> {{ auth()->user()->email }}
        <span class="text-muted ms-2">as requested by {{ session('impersonator_email') }}</span>
      </div>
      <form method="post" action="{{ route('admin.impersonate.stop') }}">
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

<script>
  // Setup CSRF header for any jQuery AJAX calls
  (function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (window.jQuery && token) {
      $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
    }
  })();
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => el.remove());
  }, 5000);
</script>

<!-- Place for page-specific scripts -->
@stack('scripts')
</body>
</html>
