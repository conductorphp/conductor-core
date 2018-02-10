<?php

namespace ConductorCore\Filesystem\MountManager\Plugin;

use ConductorCore\Filesystem\MountManager\MountManager;

interface SyncPluginInterface
{
    /**
     * @param MountManager $mountManager
     * @param string       $from
     * @param string       $to
     * @param array        $config
     */
    public function sync(MountManager $mountManager, string $from, string $to, array $config = []): void;
}
