<?php

namespace ConductorCore;


return [
    'adapters' => [
        'local' => [
            'class'     => Filesystem\Adapter\LocalAdapter::class,
            'arguments' => [
                'root'          => '/',
                'write_flags'   => LOCK_EX,
                'link_handling' => \League\Flysystem\Adapter\Local::SKIP_LINKS,
                'permissions'   => [],
            ],
        ],
    ],
];
