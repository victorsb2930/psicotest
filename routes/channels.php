<?php
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Aquí se registran los canales de broadcasting que soporta tu aplicación.
| La ruta POST /broadcasting/auth se genera al invocar Broadcast::routes().
| Asegúrate de tener sesión iniciada para canales privados.
*/

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
