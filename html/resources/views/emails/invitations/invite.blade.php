<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invitation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* Minimal inline-safe styles for email clients */
    .btn {
      display:inline-block;
      padding:12px 18px;
      border-radius:6px;
      background:#2d6cdf;
      color:#ffffff !important;
      text-decoration:none;
      font-weight:600;
    }
    .text-muted { color:#6c757d; }
    .small { font-size:12px; }
    .container { max-width:600px;margin:0 auto;padding:24px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111; }
    h1 { font-size:20px;margin:0 0 16px; }
    p { line-height:1.5;margin:0 0 12px; }
    .card { border:1px solid #eee; border-radius:8px; padding:20px; }
    .footer { margin-top:24px; color:#6c757d; font-size:12px; }
  </style>
</head>
<body>
<div class="container">
  <h1>You're invited to {{ $tenantName }} on {{ config('app.name') }}</h1>

  <div class="card">
    <p>Hello,</p>
    <p>
      You’ve been invited to join <strong>{{ $tenantName }}</strong>
      @if(!empty($roleName))
        as <strong>{{ $roleName }}</strong>
      @endif
      on <strong>{{ config('app.name') }}</strong>.
    </p>

    @if(!empty($expiresAt))
      <p class="text-muted small">
        This invitation expires on <strong>{{ $expiresAt->toDayDateTimeString() }}</strong>.
      </p>
    @endif

    <p style="margin-top:16px; margin-bottom:16px;">
      <a href="{{ $acceptUrl }}" class="btn">Accept invitation</a>
    </p>

    <p class="small text-muted">
      If the button doesn’t work, copy and paste this URL into your browser:<br>
      <span style="word-break:break-all;">{{ $acceptUrl }}</span>
    </p>
  </div>

  <div class="footer">
    <p>Sent by {{ config('app.name') }} • Please ignore this email if you weren’t expecting it.</p>
  </div>
</div>
</body>
</html>
