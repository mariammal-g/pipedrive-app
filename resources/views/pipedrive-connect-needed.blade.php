<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:Arial;padding:12px">
  <h3>Connect Pipedrive</h3>
  <p>We don’t have a saved connection for this Pipedrive account.</p>
  <p><a href="{{ $connectUrl }}" target="_blank">Click here to connect</a></p>
  @isset($error) <p style="color:red">{{ $error }}</p>@endisset
</body>
</html>
