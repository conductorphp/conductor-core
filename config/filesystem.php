<?php

return [
    'adapters' => [
        'local' => [
            'class'     => \ConductorCore\Filesystem\Adapter\LocalPlugin::class,
            'arguments' => [
                'root'          => '/',
                'write_flags'   => LOCK_EX,
                'link_handling' => \League\Flysystem\Adapter\Local::SKIP_LINKS,
                'permissions'   => [],
            ],
        ],
    ],
];
