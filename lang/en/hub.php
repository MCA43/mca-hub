<?php

return [
    'app' => [
        'title' => 'MCA Hub',
        'subtitle' => 'Installed and available MCA packages for your stack',
    ],
    'meta' => [
        'framework' => 'Detected stack',
        'catalog_updated' => 'Catalog updated: :date',
        'catalog_local' => 'Using bundled catalog',
        'catalog_remote' => 'Remote catalog',
        'catalog_github' => 'GitHub: :org (mca-* repos)',
    ],
    'status' => [
        'installed' => 'Installed',
        'available' => 'Available',
        'planned' => 'Planned',
    ],
    'card' => [
        'open' => 'Open',
        'install_hint' => 'Install',
        'version' => 'v:version',
        'frameworks' => 'Stacks',
        'github' => 'GitHub',
        'not_routed' => 'Installed but UI route not registered',
    ],
    'empty' => 'No MCA packages listed for :framework yet.',
    'errors' => [
        'root_only' => 'This area is for root users only.',
    ],
];
