<?php


Broadcast::channel('stands.feria.{idFeria}', function ($user, int $idFeria) {
    return $user !== null;
});



Broadcast::channel('pagos.feria.{idFeria}', function ($user, int $idFeria) {
    return $user !== null;
});

Broadcast::channel('contratos.feria.{idFeria}', function ($user, int $idFeria) {
    return $user !== null;
});

Broadcast::channel('admin.notifications', function ($user) {
    return $user !== null;
});
