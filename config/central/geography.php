<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Référentiel géographique central
    |--------------------------------------------------------------------------
    |
    | Métadonnées utilisées par GeographyImportService::loadLocal().
    | La version n'est pas saisie ici : elle est calculée à partir de
    | l'empreinte SHA-256 des fichiers JSON.
    |
    */
    'reference' => [
        'source' => 'local-json:database/data/geography/dz',
        'version' => null,
        'effective_at' => null,
    ],
];
