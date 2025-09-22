<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Confirm unsubscribe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:2rem}form{margin-top:1rem}</style>
</head>
<body>
<h1>Unsubscribe</h1>
<p>We’re about to unsubscribe <strong>{{ $email }}</strong> from future emails.</p>
<form method="post" action="{{ route('unsubscribe.confirm') }}">
  @csrf
  <button type="submit">Confirm unsubscribe</button>
</form>
<p style="margin-top:1rem;"><small>If this wasn’t you, simply close this page.</small></p>
</body>
</html>
