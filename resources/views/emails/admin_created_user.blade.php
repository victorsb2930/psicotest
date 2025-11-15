<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cuenta creada</title>
</head>
<body>
  <p>Hola {{ $user->name }},</p>
  <p>Se ha creado una cuenta para ti en <strong>PiscoGuía</strong> por un administrador.</p>
  <p>Tus credenciales temporales son:</p>
  <ul>
    <li><strong>Email:</strong> {{ $user->email }}</li>
    <li><strong>Contraseña temporal:</strong> {{ $password }}</li>
  </ul>
  <p>Por favor inicia sesión en <a href="{{ url('/') }}">{{ url('/') }}</a> y cambia tu contraseña desde tu perfil lo antes posible.</p>
  <p>Si no solicitaste esta cuenta, contacta con el administrador.</p>
  <p>Saludos,<br>PiscoGuía</p>
</body>
</html>
