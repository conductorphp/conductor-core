<?php

use ConductorCore\Shell\Adapter\LocalShellAdapter;

return [
    'adapters' => [
        'local' => [
            'class' => LocalShellAdapter::class,
        ],
    ],
];
