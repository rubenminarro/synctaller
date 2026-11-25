<?php

return [

    // Tasa de IVA con la que nacen las líneas de la factura (0, 5 o 10).
    'default_tax_rate' => 10,

    // Decimales de los importes facturados: el guaraní no usa decimales.
    'decimals' => 0,

    // Porcentaje del IVA de la factura que se sugiere como retención de IVA (M12, G3):
    // las aseguradoras retienen el 30 %. Es solo una sugerencia: se registra el importe del comprobante.
    'vat_withholding_percentage' => 30,

];
