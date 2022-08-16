<?php

return [
    'adapters' => [
        'local' => [
            'class'     => \League\Flysystem\Local\LocalFilesystemAdapter::class,
            'arguments' => [
                'location'          => '/',
                'linkHandling' => \League\Flysystem\Local\LocalFilesystemAdapter::SKIP_LINKS,
            ],
        ],
    ],
];
