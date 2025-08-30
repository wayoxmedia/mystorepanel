@if(auth()->check() && method_exists(auth()->user(), 'hasVerifiedEmail') && ! auth()->user()->hasVerifiedEmail())
  <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center" role="alert">
    <div class="me-3">
      <div class="fw-semibold">Verify your email</div>
      <div class="small">
        We sent a verification link to
        <span class="fw-semibold">{{ auth()->user()->email }}</span>.
        If you didn’t receive it, you can resend it now.
      </div>
    </div>

    <form method="post" action="{{ route('verification.send') }}" class="mt-2 mt-sm-0">
      @csrf
      <button class="btn btn-sm btn-outline-dark">Resend verification</button>
    </form>
  </div>
@endif
