<?php

return [
    'adapters' => [
        'local' => [
            'class'     => \League\Flysystem\Adapter\Local::class,
            'arguments' => [
                'root'          => '/',
                'write_flags'   => LOCK_EX,
                'link_handling' => \League\Flysystem\Adapter\Local::SKIP_LINKS,
                'permissions'   => [],
            ],
        ],
    ],
];
