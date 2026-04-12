<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant par d\u00e9faut
    |--------------------------------------------------------------------------
    | Utilis\u00e9 quand aucun header X-Tenant ni sous-domaine n'est d\u00e9tect\u00e9.
    | Mettre le slug du tenant principal (ex: "burkina-faso").
    */
    'default' => env('PGA_DEFAULT_TENANT', 'burkina-faso'),
];
