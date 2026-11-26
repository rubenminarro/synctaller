<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios a clientes y cuentas por pagar (requiere schedule:run cada minuto y queue:work). La hora es de cada
// taller: el comando la compara en cada uno y le despacha un job cuando llega, una vez por día (T3, paso 5; historial
// 2.97). Sin taller activo ni consultas a la conexión por defecto (fallo cerrado, TD10).
Schedule::command('notifications:send-reminders --due')
    ->everyMinute()
    ->withoutOverlapping();
