<?php

namespace DevopsToolCore\Filesystem\MountManager\Plugin;

use DevopsToolCore\Filesystem\MountManager\MountManager;

interface SyncPluginInterface
{
    /**
     * @param MountManager $mountManager
     * @param              $from
     * @param              $to
     * @param array        $config
     *
     * @return void
     */
    public function sync(MountManager $mountManager, $from, $to, array $config = []);
}
