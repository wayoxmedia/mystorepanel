<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Unsubscribed</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:2rem}</style>
</head>
<body>
<h1>Unsubscribed</h1>
<p><strong>{{ $email }}</strong> has been unsubscribed.</p>
@if(($affected ?? 0) === 0)
  <p><small>No active subscription was found for this address.</small></p>
@endif
</body>
</html>
