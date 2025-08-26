@if (session('success'))
  <div class="alert alert-success {{ session('nd') ?? '' }}">
    {{ session('success') }}
  </div>
@endif
@if (session('error'))
  <div class="alert alert-danger{{ session('nd') ?? '' }}">
    {{ session('error') }}
  </div>
@endif
