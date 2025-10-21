<?php

return [
    'adapters' => [
        'local' => [
            'class'     => \League\Flysystem\Local\LocalFilesystemAdapter::class,
            'arguments' => [
                'location'     => '/',
                'visibility'   => new \League\Flysystem\UnixVisibility\PortableVisibilityConverter(
                    filePublic: 0644,
                    filePrivate: 0600,
                    directoryPublic: 0755,
                    directoryPrivate: 0700,
                    defaultForDirectories: \League\Flysystem\Visibility::PUBLIC
                ),
                'linkHandling' => \League\Flysystem\Local\LocalFilesystemAdapter::SKIP_LINKS,
            ],
        ],
    ],
];
