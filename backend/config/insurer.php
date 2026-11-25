<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modalidad neta del deducible (M10)
    |--------------------------------------------------------------------------
    |
    | Habilita facturar siniestros con deducible mayor a 0 con la modalidad
    | neta: la aseguradora recibe lo cubierto menos el deducible y el dueño, el
    | deducible en una línea propia, como máximo lo cubierto (V16). Encendida
    | por defecto desde el 2026-11-25: la confirmó la contadora (V21) y V16 ya
    | está implementado (historial 2.89). Se conserva para poder apagarla sin
    | tocar código: apagada, rige la regla A (con deducible mayor a 0 no se
    | factura ni se entrega, aunque el siniestro tenga la modalidad cargada).
    |
    */

    'deductible_net_mode' => (bool) env('INSURER_DEDUCTIBLE_NET_MODE', true),

];
