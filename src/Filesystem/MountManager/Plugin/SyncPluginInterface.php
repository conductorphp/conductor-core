<?php

namespace ConductorCore\Filesystem\MountManager\Plugin;

use ConductorCore\Filesystem\MountManager\MountManager;

interface SyncPluginInterface
{
    /**
     * @param MountManager $mountManager
     * @param string $from
     * @param string $to
     * @param array $config
     *
     * @return bool True if all operations succeeded; False if any operations failed
     */
    public function sync(MountManager $mountManager, string $from, string $to, array $config = []): bool;
}
